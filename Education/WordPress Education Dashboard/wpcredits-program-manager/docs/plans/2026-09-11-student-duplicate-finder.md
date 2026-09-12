# Student Duplicate Finder: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship plugin 1.102.0 with the Student Duplicate Finder: a module under Modules that reads Students, Students Reports and Feedback every three hours, lists every student who has more than one row with a proposal for each row, and deletes the rows a program manager ticks and confirms, reading them again first and keeping a sealed copy of each for 30 days. Deleting ships switched off.

**Architecture:** Six classes under `includes/tools/`, one job each. `WPCPM_Duplicate_Rules` is pure: the grouping key, the proposals and verdicts, the expansion of a posted selection and the check made again before a delete. `WPCPM_Duplicates_Scan` is the fifth scheduled sync and keeps the report. `WPCPM_Duplicate_Vault` keeps the sealed copies and the log they become. `WPCPM_Duplicate_Delete` runs one confirmed delete, from a selection to an outcome. `WPCPM_Duplicate_Finder` is the tool: its hooks, its handlers and which page to draw. `WPCPM_Duplicate_Finder_Screen` prints. Around them, the Airtable client gains `delete_records()`, the request readers `posted_list()`, the settings a switch, and the Administrator Dashboard a thirteenth tile with a card.

**Tech Stack:** PHP 7.4-compatible WordPress plugin (WordPress 6.5 floor); standalone suites under `bin/test-*.php` that stub WordPress at their top; `bash bin/check-standards.sh` (phpcs with the WordPress Coding Standards, and the dash rule), `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php`; `php bin/build-docs.php` for the guides; WP-CLI for the translation template (`sh bin/make-pot.sh`); `bash bin/build` for the zip.

**Spec:** `docs/specs/2026-09-11-student-duplicate-finder-design.md`, approved by the product owner in four parts on 11 September 2026. The commit that adds this plan corrects it in three places, none of them a decision: section 4.1 (the scan keeps how many work columns are filled, not their names), section 5.2 (the work columns are every track's, the Track Builder's included) and section 11 (the suites, as the tasks below split them). Every section or decision number below is that document's.

**Proven before it was written:** every line of code in this plan was run on 11 September 2026 in a scratch clone of `main` at `92d4fca` (1.101.1), one commit per task. Each commit was then checked again on its own: the suites it touches, run against the commit before with only its test changes in place, printed the failure its Step 2 quotes; and at the commit every suite was silent (88 on `main`, 93 at the end), the three static checks were clean, and `bash bin/check-standards.sh` ended `85 warnings, no errors.` Then this document itself was followed from a fresh checkout of `92d4fca`, every new file saved as given and every diff put through `git apply`: each task's tree came out identical to its commit in the clone, the translation template aside, which Task 11 regenerates. Nothing was read from or written to Airtable for this plan, and every fixture is synthetic. A check that fails while following this plan is a difference from that run, not a flaw to work around.

## Global Constraints

- **Start from `main` at 1.101.1.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.101.1` and `git status --short` prints nothing. Then work in a worktree of its own (spec 12): `git worktree add ../duplicate-finder/wpcredits-program-manager -b duplicate-finder`. The folder keeps the plugin's name because `bin/build` names the zip, and the one folder inside it, after the folder it runs in.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible: no `match`, no named arguments, no union types. Every string a person reads is US English and names the products in full ("Student Duplicate Finder", "Administrator Dashboard", "Student Report Card"). No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen.
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` Four things it counts are easy to trip: the `=` of consecutive assignments align, the arrows of a multi-line array align, an associative array of more than one item takes one line an item, and a comment line between two assignments ends their alignment block.
- **Test first, every task:** apply the checks, run them, see them fail as Step 2 says, then write the code.
- **Nothing in the implementation touches Airtable.** The suites stand in for it, and the fixtures are synthetic: `example.test` addresses and record IDs that spell what they are (`recSTUOLD00000000`). `bin/` and `docs/` never ship in the zip but are public on the mirror, so no file this plan adds holds a student's name or address, or an Airtable record ID that is not an obvious placeholder. Once Task 11 deploys, the live site's scan reads the base every three hours as the syncs do; nothing writes to the base before Task 12, and Task 12 only on the product owner's yes.
- **Deleting ships switched off** (decision 3.10). No task turns it on; the product owner does, in Task 12.
- The version moves in Task 11 only: 1.102.0. The Track Builder's phase T2b is planned as the next minor too; if another release has taken 1.102.0 by the time this merges, this one takes the next free minor, and Task 11's heading and version strings follow.
- Comments explain why and name the decision or the bug behind a rule. Every commit message starts with "Student Duplicate Finder:" and ends with the `Co-Authored-By:` trailer of whoever made it.
- Every class file under `includes/tools/` is required by both `wpcredits-program-manager.php` and `uninstall.php`, in the same order: `bin/test-roles.php` holds the two lists parallel, and `bin/test-handlers.php` loads what the first one names.

## What this plan decides

The spec leaves these to the plan, or to the code:

1. **The delete is a class of its own.** `WPCPM_Duplicate_Delete::run( $keys, $pairs, $actor )` holds spec 7.3 from step 2 on; the tool's handler checks the capability and the nonce (step 1) and hands over. So every refusal is tested without a request, in `bin/test-duplicate-delete.php`, and the nonce tied to the set is tested where the request is, in `bin/test-duplicate-finder.php`.
2. **The markup is a class of its own.** `WPCPM_Duplicate_Finder_Screen` takes every fact it prints as an argument and reads nothing else, so the tool decides and the screen words. The rules speak in codes and the screen owns the sentences, one per code, so the list, the confirmation and the notice after a delete cannot word one row two ways.
3. **The confirmation is a page, not a change.** Review selection posts to the finder's own screen under the nonce `wpcpm_duplicates_review`; Delete posts to `admin-post.php` under `wpcpm_duplicates_delete_` followed by `selection_hash()` of the posted keys and pairs (sorted, joined, the first sixteen hex characters of their md5). Back to the list is a form of its own that carries the selection back, never in the URL.
4. **The scan keeps reduced rows, and `work` is a count.** About 3,100 rows sit in the scan's state between ticks, and the rules and the screen only ask how many (spec 4.1, as corrected).
5. **A table that answers with no rows ends the scan as a failure**, the last list kept: it is a renamed table or a revoked token, not a base without students, and a report built on it would say nobody is duplicated.
6. **The site references are matched on the meta value**, in user meta and post meta alike, so a key a later release adds is covered the day it lands. The finder's own copies (`wpcpm_dup_copy`) are left out, or a copy waiting to be settled would lock the row it was made for.
7. **A rate limit is reported in Airtable's own sentence,** which says how long to wait: that is the notice's "when to try again" (spec 7.5).
8. **The card sits after Offers running low**, the tile before it on the strip, so the tiles and the cards keep one order. Its anchor is `duplicates`.

## File map

| File | Task | Responsibility |
| --- | --- | --- |
| `includes/tools/class-wpcpm-duplicate-rules.php` | 1 | The rules: the key, the proposals, the verdicts, the expansion of a selection, the check made again |
| `includes/class-wpcpm-airtable.php` | 2 | `delete_records()`: ten a request, and how far a stopped run got |
| `includes/class-wpcpm-request.php` | 3 | `posted_list()`: a ticked list, each value whole or not at all |
| `includes/tools/class-wpcpm-duplicates-scan.php` | 4 | The scan: three tables every three hours, the report, the site references |
| `includes/tools/class-wpcpm-duplicate-vault.php` | 5 | The sealed copies, the daily job, the log |
| `includes/class-wpcpm-settings.php`, `includes/class-wpcpm-admin.php` | 6 | The switch, `duplicate_delete_enabled`, off by default |
| `includes/tools/class-wpcpm-duplicate-delete.php` | 7 | One confirmed delete, from a selection to an outcome |
| `includes/tools/class-wpcpm-duplicate-finder.php` | 8 | The tool: hooks, handlers, which page to draw |
| `includes/tools/class-wpcpm-duplicate-finder-screen.php` | 8 | The list, the confirmation, a copy, the notice and the log |
| `assets/js/duplicate-finder.js`, `assets/css/duplicate-finder.css` | 8 | The live count, Select all ready and Clear; the screen's own rules on top of `admin.css` |
| `includes/class-wpcpm-tools.php` | 8 | The finder registered as a tool |
| `includes/modules/class-wpcpm-administrators-cards.php`, `includes/modules/class-wpcpm-administrators-dashboard.php`, `includes/class-wpcpm-return.php` | 9 | The thirteenth tile, its card and its anchor |
| `docs/sections/32-admin-tools.md`, and `docs/administrators.md` and `docs/build/administrators.html` as `bin/build-docs.php` writes them | 10 | The program managers' guide |
| `wpcredits-program-manager.php`, `uninstall.php` | 1, 4, 5, 7, 8, 11 | The two require lists, kept parallel; the version |
| `readme.txt`, `languages/wpcredits-program-manager.pot` | 11 | The changelog and stable tag, the translation template |
| `bin/test-duplicate-rules.php`, `bin/test-duplicates-scan.php`, `bin/test-duplicate-vault.php`, `bin/test-duplicate-delete.php`, `bin/test-duplicate-finder.php` | 1, 4, 5, 7, 8 | The five new suites |
| `bin/test-airtable.php`, `bin/test-request.php`, `bin/test-sponsors-sync.php`, `bin/test-settings.php`, `bin/test-roles.php`, `bin/test-handlers.php`, `bin/test-administrators-dashboard.php`, `bin/test-return.php`, `bin/test-handbook.php` | 2 to 10 | The suites the change reaches |

## How to apply a change

Every change to an existing file below is a unified diff against that file as the task before left it, and every new file is given whole. Apply a diff by saving it to a file outside the plugin folder and running `git apply <file>` from the plugin root; `git apply --check <file>` first says whether it fits. Do not retype a diff by hand: several of its lines differ from their neighbors only by alignment spaces, which phpcs counts. A new file is saved exactly as given, tabs included. The failures each Step 2 quotes give paths from the plugin root and PHP's include path as `...`; the machine running them prints its own.

"Run everything" in a task's Step 5 means the five commands of the Global Constraints: the battery loop, the three static checks and `bash bin/check-standards.sh`.

---

### Task 1: The rules

Which of a student's rows may be deleted, and why the others stay: spec section 5, as a pure function (decision 3.3). No WordPress function, no request and no clock, so every rule is tested on fixtures, and the scan, the screen and the delete all ask the same class. Reasons are codes with their facts, never sentences: Task 8's screen words them.

**Files:**
- Create: `includes/tools/class-wpcpm-duplicate-rules.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (one `require_once` each, after the Mentor Status Checker's)
- Test: `bin/test-duplicate-rules.php` (new)

**Interfaces:**
- Consumes: nothing.
- Produces: `final class WPCPM_Duplicate_Rules`, with the constants `TABLES` (`students`, `reports`, `feedback`), `DELETE_ORDER` (`feedback`, `reports`, `students`), `COLUMNS` (each table's name, status, institution, mentor, start, end, hours and notes columns), `EMAIL` (`Email`), `GRADUATED`, `ABANDONED`, `PAUSED`, `FEEDBACK_IDENTITY`, `MAX_ROWS` (100), the proposals `KEEP`, `DELETE` and `REVIEW`, the verdicts `READY` and `DECIDE`, and `HOLDS`; and the static methods:
  - `key( $email )`: the first sixteen hex characters of the md5 of the trimmed, lowercased address, or `''` for none.
  - `is_filled( $value )` and `work_filled( $table, array $fields, array $work_columns )` (column names, sorted).
  - `reduce( $table, array $record, array $context )`: `id`, `created`, `email`, `name`, `status`, `institution` (record IDs), `mentor` (bool), `start`, `end`, `hours`, `notes` (bool) and `work` (a count).
  - `classify( array $group, array $refs, array $context )`: `verdict`, `name`, `rows` (table => rows, oldest first, each with `proposal`, `locked`, `selectable`, `reasons` and `codes`), `counts` (table => n) and `flags` (`refire`, `pending`, `spelling`).
  - `expand( array $report, array $student_keys, array $pairs )`: `rows` (each `key`, `table`, `id`, `via` and the scan's `codes`) and `dropped` (each `code`: `unknown`, `not-ready`, `not-selectable` or `limit`).
  - `recheck( array $selection, array $live, array $refs, array $context )`: `go` (selection rows) and `refused` (each `key`, `table`, `id` and `code`: `gone`, `site`, `changed` or `last-row`).

- [ ] **Step 1: Write the failing checks.** Create `bin/test-duplicate-rules.php`:

```php
<?php
/**
 * The Student Duplicate Finder's rules: which of a student's rows may be deleted, and why not.
 *
 * `WPCPM_Duplicate_Rules` is pure, so this suite loads it and nothing else: no WordPress, no
 * Airtable. Every rule of the design's section 5 is pinned on its own, by a row that differs from
 * a clean candidate in that one respect, so taking a rule out fails exactly the check that names
 * it. The shapes the read of 10 September 2026 found are pinned whole along the way.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-rules.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/includes/tools/class-wpcpm-duplicate-rules.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * A record ID that says what it is: `rec` and fourteen characters, padded with zeros.
 *
 * @param string $tag Up to fourteen letters and digits.
 * @return string
 */
function rid( $tag ) {
	return 'rec' . str_pad( strtoupper( $tag ), 14, '0' );
}

/** The work columns the scan would read from the report form, cut down to two. */
$work = array( 'Post Reflection: Building Your Personal Website', 'Beginner WordPress User - final grade' );

/**
 * The context the scan hands over: the site's active tracked statuses, and the work columns.
 *
 * `live` is the default `student_statuses` setting word for word, Paused and Pending graduation
 * included, because that is what `WPCPM_Mentors_Sync::tracked_statuses()` hands the scan.
 */
$ctx = array(
	'live'         => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' ),
	'work_columns' => $work,
);

/**
 * One reduced row, from a record in the shape the API returns.
 *
 * @param string $table   One of the three tables.
 * @param string $tag     The record ID's tag.
 * @param string $created ISO 8601 created time.
 * @param array  $fields  Cells.
 * @return array
 */
function row( $table, $tag, $created, array $fields = array() ) {
	global $ctx;

	return WPCPM_Duplicate_Rules::reduce(
		$table,
		array(
			'id'          => rid( $tag ),
			'createdTime' => $created,
			'fields'      => $fields,
		),
		$ctx
	);
}

/**
 * The same student in all three tables: a first application that did not move forward, and a
 * second one that is under way. The shape the Slack request's example has.
 *
 * @param array $changes Table => tag => cells that differ from the clean shape (null removes one).
 * @return array Table => rows.
 */
function reapplied( array $changes = array() ) {
	$base = array(
		'students' => array(
			'stuold' => array( '2026-01-27T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'Not moving forward', 'Educational Institutions' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
			'stunew' => array( '2026-09-09T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'In Sensei', 'Educational Institutions' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
		),
		'reports'  => array(
			'repold' => array( '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'Not moving forward', 'Educational institution' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
			'repnew' => array( '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'In Sensei', 'Educational institution' => array( rid( 'inst' ) ), 'Mentor' => array( rid( 'mentor' ) ) ) ),
		),
		'feedback' => array(
			'fbold'  => array( '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Institution' => array( rid( 'inst' ) ) ) ),
			'fbnew'  => array( '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Course' => 'In Sensei', 'Institution' => array( rid( 'inst' ) ) ) ),
		),
	);

	$group = array();
	foreach ( $base as $table => $rows ) {
		$group[ $table ] = array();
		foreach ( $rows as $tag => $shape ) {
			$fields = isset( $changes[ $table ][ $tag ] ) ? array_merge( $shape[1], $changes[ $table ][ $tag ] ) : $shape[1];
			foreach ( $fields as $name => $value ) {
				if ( null === $value ) {
					unset( $fields[ $name ] );
				}
			}
			$group[ $table ][] = row( $table, $tag, $shape[0], $fields );
		}
	}

	return $group;
}

/**
 * The classified row with this tag.
 *
 * @param array  $classified `classify()`'s answer.
 * @param string $tag        The record ID's tag.
 * @return array|null
 */
function pick( array $classified, $tag ) {
	foreach ( $classified['rows'] as $rows ) {
		foreach ( $rows as $one ) {
			if ( rid( $tag ) === $one['id'] ) {
				return $one;
			}
		}
	}
	return null;
}

/**
 * A row's proposal and reason codes, the two things most checks read.
 *
 * @param array  $classified `classify()`'s answer.
 * @param string $tag        The record ID's tag.
 * @return array
 */
function says( array $classified, $tag ) {
	$one = pick( $classified, $tag );

	return null === $one ? array() : array( $one['proposal'], $one['codes'] );
}

/* ---- the key and the reduced row ----------------------------------------- */

echo "\n=== The key and the reduced row ===\n";

ck( 'the key compares mailboxes: case and padding do not matter', WPCPM_Duplicate_Rules::key( '  Student@Example.TEST ' ), WPCPM_Duplicate_Rules::key( 'student@example.test' ) );
ck( 'and it is sixteen hex characters, not the address', 1 === preg_match( '/^[0-9a-f]{16}$/', WPCPM_Duplicate_Rules::key( 'student@example.test' ) ), true );
ck( 'no address, no key', WPCPM_Duplicate_Rules::key( '   ' ), '' );

$reduced = row(
	'students',
	'reduced',
	'2026-01-27T10:00:00.000Z',
	array(
		'Email'                    => ' Student@example.test ',
		'Full Name'                => 'A Student',
		'Status'                   => 'In Sensei',
		'Educational Institutions' => array( rid( 'inst' ), 'not a record', array( 'id' => rid( 'inst2' ) ) ),
		'Mentor'                   => array( rid( 'mentor' ) ),
		'Start Date'               => '2026-02-01',
		'Total hours'              => 12.5,
		'Notes'                    => 'Asked to pause.',
	)
);
ck( 'a Students record reduces to what the rules read', $reduced, array(
	'id'          => rid( 'reduced' ),
	'created'     => '2026-01-27T10:00:00.000Z',
	'email'       => 'Student@example.test',
	'name'        => 'A Student',
	'status'      => 'In Sensei',
	'institution' => array( rid( 'inst' ), rid( 'inst2' ) ),
	'mentor'      => true,
	'start'       => '2026-02-01',
	'end'         => '',
	'hours'       => '12.5',
	'notes'       => true,
	'work'        => 0,
) );

$report_cells = array( 'Email' => 'a@example.test', 'Beginner WordPress User - final grade' => 88, 'Hours' => 4, 'Slack Name' => 'someone', 'Personal link' => 'https://example.test/form' );
ck( 'work on a report is a report-form column or Hours above zero, nothing else', WPCPM_Duplicate_Rules::work_filled( 'reports', $report_cells, $work ), array( 'Beginner WordPress User - final grade', 'Hours' ) );
ck( 'and the reduced row keeps the count, not the names, so a scan of three tables stays small', row( 'reports', 'r', '2026-01-01T00:00:00.000Z', $report_cells )['work'], 2 );
ck( 'Hours of zero is not work', WPCPM_Duplicate_Rules::work_filled( 'reports', array( 'Email' => 'a@example.test', 'Hours' => 0 ), $work ), array() );
ck( 'an answer is any Feedback column that is not identity, legacy ones included', WPCPM_Duplicate_Rules::work_filled( 'feedback', array( 'Email' => 'a@example.test', 'Name' => 'A', 'Course' => 'In Sensei', 'Institution' => array( rid( 'inst' ) ), 'F1 - Mentor' => array( rid( 'mentor' ) ), 'F1- Overall experience so far' => 4, 'Review' => 'Good' ), $work ), array( 'F1- Overall experience so far', 'Review' ) );
ck( 'the Students table carries neither', WPCPM_Duplicate_Rules::work_filled( 'students', array( 'Email' => 'a@example.test', 'Notes' => 'Something' ), $work ), array() );
ck( 'an empty string, an empty list and false are not filled; zero is', array( WPCPM_Duplicate_Rules::is_filled( '' ), WPCPM_Duplicate_Rules::is_filled( array() ), WPCPM_Duplicate_Rules::is_filled( false ), WPCPM_Duplicate_Rules::is_filled( 0 ) ), array( false, false, false, true ) );

/* ---- the clean case ------------------------------------------------------ */

echo "\n=== The Slack example: a first application that did not move forward, and a second ===\n";

$clean = WPCPM_Duplicate_Rules::classify( reapplied(), array(), $ctx );
ck( 'the student is Ready', $clean['verdict'], 'ready' );
ck( 'the three older rows are delete candidates', array( says( $clean, 'stuold' ), says( $clean, 'repold' ), says( $clean, 'fbold' ) ), array( array( 'delete', array( 'older' ) ), array( 'delete', array( 'older' ) ), array( 'delete', array( 'older' ) ) ) );
ck( 'and the three newest are kept', array( says( $clean, 'stunew' ), says( $clean, 'repnew' ), says( $clean, 'fbnew' ) ), array( array( 'keep', array( 'newest' ) ), array( 'keep', array( 'newest' ) ), array( 'keep', array( 'newest' ) ) ) );
ck( 'a candidate may be selected, a kept row may not', array( pick( $clean, 'stuold' )['selectable'], pick( $clean, 'stunew' )['selectable'] ), array( true, false ) );
ck( 'the card is headed with the newest Students row\'s name', $clean['name'], 'A Student' );
ck( 'the counts, and no flag', array( $clean['counts'], $clean['flags'] ), array( array( 'students' => 2, 'reports' => 2, 'feedback' => 2 ), array() ) );

$shuffled             = reapplied();
$shuffled['students'] = array_reverse( $shuffled['students'] );
ck( 'rows handed over newest first are sorted oldest first', says( WPCPM_Duplicate_Rules::classify( $shuffled, array(), $ctx ), 'stuold' ), array( 'delete', array( 'older' ) ) );

/* ---- each hold, alone ---------------------------------------------------- */

echo "\n=== Each reason an older row stays, alone ===\n";

/**
 * The Slack example with one difference, classified.
 *
 * @param array $changes As for `reapplied()`.
 * @param array $refs    Record ID => site references.
 * @return array
 */
function held( array $changes, array $refs = array() ) {
	global $ctx;

	return WPCPM_Duplicate_Rules::classify( reapplied( $changes ), $refs, $ctx );
}

$graduate = held( array( 'students' => array( 'stuold' => array( 'Status' => 'Graduate' ) ) ) );
ck( 'a graduation holds an older Students row', says( $graduate, 'stuold' ), array( 'review', array( 'graduation' ) ) );
ck( 'and the student then needs a decision', $graduate['verdict'], 'decide' );
ck( 'held, it can still be ticked on its own', pick( $graduate, 'stuold' )['selectable'], true );

ck( 'a live status holds an older report', says( held( array( 'reports' => array( 'repold' => array( 'Status' => 'Developer Track' ) ) ) ), 'repold' ), array( 'review', array( 'live' ) ) );
ck( 'Paused counts as live though it is no track', says( held( array( 'students' => array( 'stuold' => array( 'Status' => 'Paused' ) ) ) ), 'stuold' ), array( 'review', array( 'live' ) ) );
ck( 'a status the site does not track is not live', says( held( array( 'students' => array( 'stuold' => array( 'Status' => 'Interested' ) ) ) ), 'stuold' ), array( 'delete', array( 'older' ) ) );
ck( 'work holds an older report', says( held( array( 'reports' => array( 'repold' => array( 'Post Reflection: Building Your Personal Website' => 'https://example.test/post' ) ) ) ), 'repold' ), array( 'review', array( 'work' ) ) );
ck( 'answers hold an older Feedback row', says( held( array( 'feedback' => array( 'fbold' => array( 'F4 - What stopped you?' => 'Exams' ) ) ) ), 'fbold' ), array( 'review', array( 'answers' ) ) );
ck( 'Total hours above zero hold an older Students row', says( held( array( 'students' => array( 'stuold' => array( 'Total hours' => 30 ) ) ) ), 'stuold' ), array( 'review', array( 'hours' ) ) );
ck( 'Notes hold an older Students row', says( held( array( 'students' => array( 'stuold' => array( 'Notes' => 'Moved to the 50h course.' ) ) ) ), 'stuold' ), array( 'review', array( 'notes' ) ) );
ck( 'an institution link the newest row lacks holds the older row', says( held( array( 'feedback' => array( 'fbnew' => array( 'Institution' => null ) ) ) ), 'fbold' ), array( 'review', array( 'lacks-institution' ) ) );
ck( 'so does a mentor the newest row lacks', says( held( array( 'reports' => array( 'repnew' => array( 'Mentor' => null ) ) ) ), 'repold' ), array( 'review', array( 'lacks-mentor' ) ) );
ck( 'and a Course the newest Feedback row lacks', says( held( array( 'feedback' => array( 'fbold' => array( 'Course' => 'In Sensei' ), 'fbnew' => array( 'Course' => null ) ) ) ), 'fbold' ), array( 'review', array( 'lacks-status' ) ) );
ck( 'created in the same second as the newest holds the older row', says( WPCPM_Duplicate_Rules::classify( array( 'feedback' => array( row( 'feedback', 'fbone', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ), row( 'feedback', 'fbtwo', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ) ) ), array(), $ctx ), 'fbone' ), array( 'review', array( 'same-second' ) ) );

$site = held( array(), array( rid( 'repold' ) => array( array( 'kind' => 'wpcpm_mentor_note', 'key' => '_wpcpm_student_record', 'object' => 501 ) ) ) );
ck( 'a row the site points at is held and locked', array( says( $site, 'repold' ), pick( $site, 'repold' )['locked'], pick( $site, 'repold' )['selectable'] ), array( array( 'review', array( 'site' ) ), true, false ) );

/* ---- the status rules leave Feedback out ---------------------------------- */

echo "\n=== The status rules leave Feedback out ===\n";

ck( 'an older Feedback row whose Course is a live track is still a candidate', says( held( array( 'feedback' => array( 'fbold' => array( 'Course' => 'In Sensei' ) ) ) ), 'fbold' ), array( 'delete', array( 'older' ) ) );

/* ---- doubts about the newest ---------------------------------------------- */

echo "\n=== When the newest row is not clearly the one to keep ===\n";

$inverted = held( array( 'students' => array( 'stuold' => array( 'Status' => 'In Sensei' ), 'stunew' => array( 'Status' => 'Not moving forward' ) ) ) );
ck( 'a newest row that did not move forward while an older one is live goes to review', says( $inverted, 'stunew' ), array( 'review', array( 'inverted' ) ) );
ck( 'and the older live row says why it stays', says( $inverted, 'stuold' ), array( 'review', array( 'live', 'newest-abandoned' ) ) );
ck( 'that newest row can be ticked on its own', pick( $inverted, 'stunew' )['selectable'], true );

$older_used = held( array(), array( rid( 'repold' ) => array( array( 'kind' => 'user', 'key' => 'wpcpm_student_record_id', 'object' => 9 ) ) ) );
ck( 'when the site uses the older report, the newest goes to review instead of being kept', says( $older_used, 'repnew' ), array( 'review', array( 'site-older' ) ) );
ck( 'and the older one is locked', pick( $older_used, 'repold' )['locked'], true );

$tie = WPCPM_Duplicate_Rules::classify( array( 'students' => array( row( 'students', 'tieone', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ), row( 'students', 'tietwo', '2026-09-09T10:00:00.000Z', array( 'Email' => 'a@example.test' ) ) ) ), array(), $ctx );
ck( 'two rows created in the same second: the later ID is not "newest", it goes to review', says( $tie, 'tietwo' ), array( 'review', array( 'tie' ) ) );

/* ---- verdicts and flags --------------------------------------------------- */

echo "\n=== Verdicts and flags ===\n";

ck( 'one held row anywhere makes the whole student a decision', held( array( 'feedback' => array( 'fbold' => array( 'Review' => 'It was fine' ) ) ) )['verdict'], 'decide' );

$refire = reapplied();
unset( $refire['students'][0] );
ck( 'more report rows than Students rows is flagged', WPCPM_Duplicate_Rules::classify( $refire, array(), $ctx )['flags'], array( 'refire' ) );

$pending = reapplied();
array_pop( $pending['reports'] );
array_pop( $pending['feedback'] );
ck( 'a second Students row with no second report is flagged', WPCPM_Duplicate_Rules::classify( $pending, array(), $ctx )['flags'], array( 'pending' ) );

ck( 'two spellings of one address are flagged', WPCPM_Duplicate_Rules::classify( reapplied( array( 'feedback' => array( 'fbnew' => array( 'Email' => 'Student@Example.test' ) ) ) ), array(), $ctx )['flags'], array( 'spelling' ) );

/* ---- the selection -------------------------------------------------------- */

echo "\n=== A posted selection, against the stored report ===\n";

$ready_key  = WPCPM_Duplicate_Rules::key( 'student@example.test' );
$decide_key = WPCPM_Duplicate_Rules::key( 'other@example.test' );
$report     = array(
	'groups' => array(
		$ready_key  => $clean,
		$decide_key => $older_used,
	),
);

/**
 * A selection's rows as table, record ID and how each came in.
 *
 * @param array $rows `expand()`'s rows.
 * @return array
 */
function picked( array $rows ) {
	return array_map(
		static function ( $one ) {
			return array( $one['table'], $one['id'], $one['via'] );
		},
		$rows
	);
}

$chosen = WPCPM_Duplicate_Rules::expand( $report, array( $ready_key ), array() );
ck( 'a Ready student stands for its three candidates, through the student checkbox', picked( $chosen['rows'] ), array( array( 'students', rid( 'stuold' ), 'student' ), array( 'reports', rid( 'repold' ), 'student' ), array( 'feedback', rid( 'fbold' ), 'student' ) ) );
ck( 'and nothing is dropped', $chosen['dropped'], array() );

$chosen = WPCPM_Duplicate_Rules::expand( $report, array( $decide_key, 'ffffffffffffffff' ), array() );
ck( 'a student who needs a decision cannot be taken whole, and an unknown key is dropped', array( $chosen['rows'], array_column( $chosen['dropped'], 'code' ) ), array( array(), array( 'not-ready', 'unknown' ) ) );

$chosen = WPCPM_Duplicate_Rules::expand( $report, array(), array( 'reports:' . rid( 'repnew' ), 'reports:' . rid( 'repold' ), 'students:' . rid( 'stunew' ), 'reports:' . rid( 'repnew' ) ) );
ck( 'a selectable row is taken once; a locked or a kept one is dropped', array( picked( $chosen['rows'] ), array_column( $chosen['dropped'], 'code' ) ), array( array( array( 'reports', rid( 'repnew' ), 'row' ) ), array( 'not-selectable', 'not-selectable' ) ) );

// Thirty-four Ready students with three candidates each: 102 rows, two over the limit.
$many = array( 'groups' => array() );
for ( $i = 0; $i < 34; $i++ ) {
	$one = $clean;
	foreach ( $one['rows'] as $table => $rows ) {
		foreach ( array_keys( $rows ) as $n ) {
			// Two digits for the group: `rid()` pads with zeros, so "g1" and "g10" would meet.
			$one['rows'][ $table ][ $n ]['id'] = rid( sprintf( '%s%dg%02d', substr( $table, 0, 1 ), $n, $i ) );
		}
	}
	$many['groups'][ WPCPM_Duplicate_Rules::key( "s{$i}@example.test" ) ] = $one;
}
$chosen = WPCPM_Duplicate_Rules::expand( $many, array_keys( $many['groups'] ), array() );
ck( 'at most 100 rows go in one confirmation; the rest are dropped as over the limit', array( count( $chosen['rows'] ), count( array_keys( array_column( $chosen['dropped'], 'code' ), 'limit', true ) ) ), array( 100, 2 ) );

/* ---- the re-check just before the delete ---------------------------------- */

echo "\n=== The same questions, asked of the base just before the delete ===\n";

$selection = WPCPM_Duplicate_Rules::expand( $report, array( $ready_key ), array() )['rows'];
$now       = array( $ready_key => reapplied() );

$again = WPCPM_Duplicate_Rules::recheck( $selection, $now, array(), $ctx );
ck( 'nothing changed: all three go', array( count( $again['go'] ), $again['refused'] ), array( 3, array() ) );

$gone = $now;
unset( $gone[ $ready_key ]['feedback'][0] );
$again = WPCPM_Duplicate_Rules::recheck( $selection, $gone, array(), $ctx );
ck( 'a row no longer under its address is refused as gone', array_column( $again['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'gone' ) );

$again = WPCPM_Duplicate_Rules::recheck( $selection, $now, array( rid( 'stuold' ) => array( array( 'kind' => 'user', 'key' => 'wpcpm_student_record_id', 'object' => 9 ) ) ), $ctx );
ck( 'a row the site points at now is refused', array_column( $again['refused'], 'code', 'id' ), array( rid( 'stuold' ) => 'site' ) );

$again = WPCPM_Duplicate_Rules::recheck( $selection, array( $ready_key => reapplied( array( 'feedback' => array( 'fbold' => array( 'F1 - How easy was it to get started?' => 5 ) ) ) ) ), array(), $ctx );
ck( 'a row ticked through the student checkbox that has gained answers is refused', array_column( $again['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'changed' ) );

$held_pick = WPCPM_Duplicate_Rules::expand( array( 'groups' => array( $ready_key => $graduate ) ), array(), array( 'students:' . rid( 'stuold' ) ) )['rows'];
$again     = WPCPM_Duplicate_Rules::recheck( $held_pick, array( $ready_key => reapplied( array( 'students' => array( 'stuold' => array( 'Status' => 'Graduate' ) ) ) ) ), array(), $ctx );
ck( 'a held row ticked on its own goes when nothing new holds it', array( count( $again['go'] ), $again['refused'] ), array( 1, array() ) );

$again = WPCPM_Duplicate_Rules::recheck( $held_pick, array( $ready_key => reapplied( array( 'students' => array( 'stuold' => array( 'Status' => 'Graduate', 'Notes' => 'Graduated in June.' ) ) ) ) ), array(), $ctx );
ck( 'but not when it has gained a reason to stay since the scan', array_column( $again['refused'], 'code', 'id' ), array( rid( 'stuold' ) => 'changed' ) );

$last = $now;
unset( $last[ $ready_key ]['feedback'][1] );
$again = WPCPM_Duplicate_Rules::recheck( $selection, $last, array(), $ctx );
ck( 'a row that is now the only one its address has in the table is refused as the last row', array_column( $again['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'last-row' ) );

$both  = WPCPM_Duplicate_Rules::expand( array( 'groups' => array( $ready_key => $inverted ) ), array(), array( 'students:' . rid( 'stuold' ), 'students:' . rid( 'stunew' ) ) )['rows'];
$again = WPCPM_Duplicate_Rules::recheck( $both, array( $ready_key => reapplied( array( 'students' => array( 'stuold' => array( 'Status' => 'In Sensei' ), 'stunew' => array( 'Status' => 'Not moving forward' ) ) ) ) ), array(), $ctx );
ck( 'and ticking every row a table has refuses them all as the last row', array_column( $again['refused'], 'code', 'id' ), array( rid( 'stuold' ) => 'last-row', rid( 'stunew' ) => 'last-row' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-duplicate-rules.php`. Expected, because the class does not exist yet:

```text
PHP Warning:  require_once(includes/tools/class-wpcpm-duplicate-rules.php): Failed to open stream: No such file or directory in bin/test-duplicate-rules.php on line 21
PHP Fatal error:  Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-duplicate-rules.php' (include_path='...') in bin/test-duplicate-rules.php:21
```

- [ ] **Step 3: Write the class.** Create `includes/tools/class-wpcpm-duplicate-rules.php`:

```php
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
```

Load it, in both lists:

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -134,6 +134,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-profile.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
```

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -140,6 +140,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-handbook.
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker-profile.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-rules.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
 WPCPM_Modules::uninstall();
```

- [ ] **Step 4: Run them and see them pass.** `php bin/test-duplicate-rules.php`. Expected last line: `ALL PASS (56 checks)`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-duplicate-rules.php includes/tools/class-wpcpm-duplicate-rules.php uninstall.php wpcredits-program-manager.php
git commit -m "Student Duplicate Finder: the rules, which of a student's rows may be deleted and why the others stay"
```

---

### Task 2: The Airtable client can delete

Spec 2.5: `WPCPM_Airtable` reads, creates and updates, and has no delete. Airtable deletes at most ten records a request, named as `records[]` query arguments on a `DELETE`, and answers with each record's ID and `deleted: true`. The client's `request()` already takes a method, paces requests to five a second and honors a 429 for every process, so the new method is a loop around it.

**Files:**
- Modify: `includes/class-wpcpm-airtable.php` (`delete_records()`, after `update_records()`)
- Test: `bin/test-airtable.php`

**Interfaces:**
- Consumes: the client's `request()`, `table_url()` and `is_record_id()`.
- Produces: `WPCPM_Airtable::delete_records( $table, array $ids )`, which answers record ID => `true` for every record Airtable confirmed and was asked for. A value that is not a record ID is dropped and a repeat is sent once, ten to a request. A request that fails ends the call with a `WP_Error` carrying that request's code and message, and its data plus `deleted`: the IDs the requests before it confirmed.

- [ ] **Step 1: Write the failing checks.** Apply:

```diff
--- a/bin/test-airtable.php
+++ b/bin/test-airtable.php
@@ -498,6 +498,66 @@ ck( 'and tests a value against it', array( WPCPM_Airtable::is_record_id( 'recABC
 $mentors_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );
 ck( 'the Mentors sync aliases it rather than keeping its own copy', false !== strpos( $mentors_src, 'return WPCPM_Airtable::is_record_id( $value );' ), true );
 
+echo "\n=== delete_records(): ten at a time, only what Airtable confirms, and a stop says how far it got ===\n";
+
+// Twelve record IDs that say what they are: two batches, the second short.
+$del = array();
+for ( $i = 1; $i <= 12; $i++ ) {
+	$del[] = 'recDELETE' . str_pad( (string) $i, 8, '0', STR_PAD_LEFT );
+}
+
+/**
+ * Airtable's answer to a DELETE: every ID it deleted, flagged.
+ *
+ * @param string[] $ids Record IDs.
+ * @return array
+ */
+function deleted_answer( array $ids ) {
+	return response( 200, array( 'records' => array_map( static function ( $id ) { return array( 'id' => $id, 'deleted' => true ); }, $ids ) ) );
+}
+
+/**
+ * The record IDs one sent request named, in order.
+ *
+ * @param int $i Which request.
+ * @return string[]
+ */
+function sent_records( $i ) {
+	parse_str( (string) parse_url( (string) $GLOBALS['sent'][ $i ]['url'], PHP_URL_QUERY ), $query );
+	return isset( $query['records'] ) ? (array) $query['records'] : array();
+}
+
+fresh( 'web' );
+queue( deleted_answer( array_slice( $del, 0, 10 ) ) );
+queue( deleted_answer( array_slice( $del, 10 ) ) );
+$r = $airtable->delete_records( 'tblX', array_merge( $del, array( 'not-a-record', $del[0] ) ) );
+
+ck( 'twelve IDs go as two DELETEs, ten and then two', array( sent(), $GLOBALS['sent'][0]['args']['method'], $GLOBALS['sent'][1]['args']['method'], count( sent_records( 0 ) ), count( sent_records( 1 ) ) ), array( 2, 'DELETE', 'DELETE', 10, 2 ) );
+ck( 'as repeated records[] parameters, and never a value that is not a record ID, nor a repeat', array_merge( sent_records( 0 ), sent_records( 1 ) ), $del );
+ck( 'a DELETE carries no body', array_key_exists( 'body', $GLOBALS['sent'][0]['args'] ), false );
+ck( 'the answer maps every deleted ID to true', $r, array_fill_keys( $del, true ) );
+
+fresh( 'web' );
+queue( response( 200, array( 'records' => array( array( 'id' => $del[0], 'deleted' => true ), array( 'id' => 'recSOMEONEELSE000', 'deleted' => true ) ) ) ) );
+ck( 'only a row Airtable confirms, and only one that was asked for, is reported deleted', $airtable->delete_records( 'tblX', array( $del[0], $del[1] ) ), array( $del[0] => true ) );
+
+fresh( 'web' );
+queue( deleted_answer( array_slice( $del, 0, 10 ) ) );
+queue( response( 422, array( 'error' => array( 'type' => 'INVALID_REQUEST_UNKNOWN' ) ) ) );
+$r = $airtable->delete_records( 'tblX', $del );
+ck( 'a refused second batch is the error, carrying the ten already deleted', array( $r->get_error_code(), $r->get_error_data()['status'], $r->get_error_data()['deleted'] ), array( 'wpcpm_airtable_error', 422, array_slice( $del, 0, 10 ) ) );
+
+fresh( 'web' );
+queue( response( 429, array( 'errors' => array() ), array( 'Retry-After' => '30' ) ) );
+$r = $airtable->delete_records( 'tblX', array( $del[0] ) );
+ck( 'a rate limit on the first batch is that error, with nothing deleted', array( $r->get_error_code(), $r->get_error_data()['deleted'] ), array( 'wpcpm_airtable_rate_limited', array() ) );
+
+fresh( 'web' );
+ck( 'nothing to delete sends nothing', array( $airtable->delete_records( 'tblX', array( 'nope', '' ) ), sent() ), array( array(), 0 ) );
+
+$no_token = new WPCPM_Airtable( array( 'api_token' => '', 'base_id' => 'appTEST' ) );
+ck( 'and without a token it refuses before sending', array( $no_token->delete_records( 'tblX', array( $del[0] ) )->get_error_code(), sent() ), array( 'wpcpm_no_token', 0 ) );
+
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
 
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-airtable.php`. Expected:

```text
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Airtable::delete_records() in bin/test-airtable.php:533
```

- [ ] **Step 3: Write the method.** Apply:

```diff
--- a/includes/class-wpcpm-airtable.php
+++ b/includes/class-wpcpm-airtable.php
@@ -326,6 +326,65 @@ class WPCPM_Airtable {
 		return $updated;
 	}
 
+	/**
+	 * Delete records.
+	 *
+	 * Airtable deletes at most ten records a request, named as repeated `records[]` query
+	 * parameters, so a longer list goes ten at a time. They are appended by hand and encoded the way
+	 * `fetch_page()` appends `fields[]`: `http_build_query()` would number them, `records[0]=`, and
+	 * that is not the parameter Airtable reads.
+	 *
+	 * **A stopped run says how far it got.** The Student Duplicate Finder keeps a copy of each row
+	 * before it asks for the delete and settles the copy only for the rows Airtable confirms, so a
+	 * failure part of the way through hands back the rows already deleted, in the error's data under
+	 * `deleted`, instead of losing them the way a plain error would. Only what Airtable confirms is
+	 * reported deleted: a row it does not list in its answer is not counted as gone.
+	 *
+	 * @param string   $table Table ID or name.
+	 * @param string[] $ids   Record IDs; anything else, and any repeat, is dropped before sending.
+	 * @return array|WP_Error Map of record ID => true for every row Airtable deleted, or the first
+	 *                        failing batch's error with the IDs deleted before it under `deleted`.
+	 */
+	public function delete_records( $table, array $ids ) {
+		$guard = $this->guard();
+		if ( is_wp_error( $guard ) ) {
+			return $guard;
+		}
+
+		$ids = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $ids ) ), array( __CLASS__, 'is_record_id' ) ) ) );
+
+		if ( empty( $ids ) ) {
+			return array();
+		}
+
+		$deleted = array();
+
+		foreach ( array_chunk( $ids, 10 ) as $chunk ) {
+			$params = array();
+
+			foreach ( $chunk as $id ) {
+				$params[] = 'records%5B%5D=' . rawurlencode( $id );
+			}
+
+			$response = $this->request( $this->table_url( $table ) . '?' . implode( '&', $params ), 'DELETE' );
+
+			if ( is_wp_error( $response ) ) {
+				$data            = (array) $response->get_error_data();
+				$data['deleted'] = array_keys( $deleted );
+
+				return new WP_Error( $response->get_error_code(), $response->get_error_message(), $data );
+			}
+
+			foreach ( isset( $response['records'] ) && is_array( $response['records'] ) ? $response['records'] : array() as $record ) {
+				if ( is_array( $record ) && ! empty( $record['deleted'] ) && isset( $record['id'] ) && in_array( (string) $record['id'], $chunk, true ) ) {
+					$deleted[ (string) $record['id'] ] = true;
+				}
+			}
+		}
+
+		return $deleted;
+	}
+
 	/**
 	 * Read the base schema, including each field's description.
 	 *
```

- [ ] **Step 4: Run them and see them pass.** `php bin/test-airtable.php`. Expected last line: `ALL PASS`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-airtable.php includes/class-wpcpm-airtable.php
git commit -m "Student Duplicate Finder: the Airtable client can delete, ten at a time, and says how far a stopped run got"
```

---

### Task 3: A ticked list, each value whole or not at all

The screen posts its ticks as lists, `wpcpm_students[]` and `wpcpm_rows[]`. `WPCPM_Request`'s readers each take one value, and a sanitizer repairs a value into another one; a repaired key could look up a different row from the one that was ticked, so a value that does not match its pattern whole is dropped instead.

**Files:**
- Modify: `includes/class-wpcpm-request.php` (`posted_list()`, last)
- Test: `bin/test-request.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `WPCPM_Request::posted_list( $name, $pattern )`: the posted values that match the pattern in full, each once, in the order posted; nothing for a field that is not a list, and no value that is not a string. Like the class's other readers it verifies nothing itself: the caller has checked the capability and the nonce.

- [ ] **Step 1: Write the failing checks.** Apply:

```diff
--- a/bin/test-request.php
+++ b/bin/test-request.php
@@ -72,5 +72,19 @@ ck( 'an absent field is the fallback', WPCPM_Request::posted_verbatim( 'missing'
 ck( 'the lines variant trims each line and drops the empty ones', WPCPM_Request::posted_verbatim_lines( 'lines' ), "A-1\nB%202" );
 ck( 'and its absent field is the fallback too', WPCPM_Request::posted_verbatim_lines( 'missing', 'none' ), 'none' );
 
+echo "\n=== posted_list(): a ticked list, each value whole or not at all ===\n";
+
+$_POST = array(
+	'keys'   => array( '0123456789abcdef', 'not a key', '0123456789ABCDEF', array( 'nested' ), '0123456789abcdef', 'fedcba9876543210' ),
+	'single' => '0123456789abcdef',
+);
+
+ck( 'values that match whole are kept once, in the order posted', WPCPM_Request::posted_list( 'keys', '/^[0-9a-f]{16}$/' ), array( '0123456789abcdef', 'fedcba9876543210' ) );
+ck( 'a field that is not a list is no list at all', WPCPM_Request::posted_list( 'single', '/^[0-9a-f]{16}$/' ), array() );
+ck( 'an absent field is an empty list', WPCPM_Request::posted_list( 'missing', '/^[0-9a-f]{16}$/' ), array() );
+
+$_POST = array( 'rows' => array( 'students:recABCDEFGHIJKLMN', "students:recABCDEFGHIJKLMN\n", 'students:recABCDEFGHIJKLMN<b>', 'reports:recABCDEFGHIJKLMO' ) );
+ck( 'nothing is repaired into a match: a trailing newline or markup drops the value', WPCPM_Request::posted_list( 'rows', '/^(students|reports|feedback):rec[A-Za-z0-9]{14}$/D' ), array( 'students:recABCDEFGHIJKLMN', 'reports:recABCDEFGHIJKLMO' ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-request.php`. Expected:

```text
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Request::posted_list() in bin/test-request.php:82
```

- [ ] **Step 3: Write the reader.** Apply:

```diff
--- a/includes/class-wpcpm-request.php
+++ b/includes/class-wpcpm-request.php
@@ -276,4 +276,37 @@ class WPCPM_Request {
 
 		return implode( "\n", $lines );
 	}
+
+	/**
+	 * A posted list, each value kept only when the whole of it matches a pattern.
+	 *
+	 * The checkbox lists a form posts as `name[]`. Nothing is cleaned into something else: the
+	 * values are keys and record IDs a handler looks up, and a value a sanitizer repaired could look
+	 * up a different row from the one that was ticked, so a value that does not match is dropped.
+	 * A field that is not a list, a value that is not a string and a repeat are dropped too.
+	 *
+	 * Same standing as the rest of this class: the handler has already checked the nonce and the
+	 * capability, and what this returns is still matched against what the site holds.
+	 *
+	 * @param string $name    Field name, without the brackets.
+	 * @param string $pattern A regular expression each value must match in full, anchored.
+	 * @return string[] The matching values, each once, in the order posted.
+	 */
+	public static function posted_list( $name, $pattern ) {
+		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
+		if ( ! isset( $_POST[ $name ] ) || ! is_array( $_POST[ $name ] ) ) {
+			return array();
+		}
+
+		$values = array();
+
+		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; each value is kept only if the pattern matches it whole.
+		foreach ( wp_unslash( $_POST[ $name ] ) as $value ) {
+			if ( is_string( $value ) && 1 === preg_match( $pattern, $value ) ) {
+				$values[ $value ] = true;
+			}
+		}
+
+		return array_keys( $values );
+	}
 }
```

- [ ] **Step 4: Run them and see them pass.** `php bin/test-request.php`. Expected last line: `ALL PASS (19 checks)`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-request.php includes/class-wpcpm-request.php
git commit -m "Student Duplicate Finder: the request reads a ticked list, each value whole or not at all"
```

---

### Task 4: The scan

The fifth scheduled sync (decision 3.2), in the shape of `WPCPM_Sponsors_Sync`, 150 minutes into the three-hour cycle the syncs share. It departs from them in one place on purpose: **a failed read ends the run** and keeps the last report (decision 3.4), where a sync keeps its state and stays running until somebody cancels. The report holds duplicates only (decision 3.5), and the site references are read once for the rows it keeps.

**Files:**
- Create: `includes/tools/class-wpcpm-duplicates-scan.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (a `require_once` each, after the rules')
- Test: `bin/test-duplicates-scan.php` (new); `bin/test-sponsors-sync.php`, whose check that the syncs take distinct minutes now counts the scan as the fifth

**Interfaces:**
- Consumes: Task 1's `reduce()`, `key()`, `classify()`, `TABLES`, `READY`, `DELETE` and `REVIEW`; `WPCPM_Airtable::fetch_page()` and `is_record_id()`; `WPCPM_Settings::get()` and `is_connected()`; `WPCPM_Students_Sync::register_interval()`, `cycle_start()` and `EVERY_THREE_HOURS`; `WPCPM_Mentors_Sync::tracked_statuses()`; `WPCPM_Program::labels()` and `track()`; `WPCPM_Student_Report_Form::fields()`.
- Produces: `final class WPCPM_Duplicates_Scan`, with:
  - the constants `CRON_SCAN` (`wpcpm_duplicates_scan`), `CRON_TICK` (`wpcpm_duplicates_tick`), `OPT_STATE`, `OPT_REPORT`, `OPT_LAST`, `OPT_ERROR` and `OPT_LOCK` (`wpcpm_duplicates_state`, `_report`, `_last_scan`, `_last_error`, `_lock`), `BUDGET` (18), `BUDGET_AJAX` (8), `LOCK_TIMEOUT` (120), `SCHEDULE_OFFSET_MINUTES` (150), `REPORT_VERSION` (1), `TABLE_SETTINGS` (table => the setting naming it) and `OWN_POST_TYPES` (`wpcpm_dup_copy`);
  - `register_cron()`, `schedule()`, `activate()`, `deactivate()`, `uninstall()`, `cron_scan()`, `start( $run_first_tick = false )` (true, or a `WP_Error` when Airtable is not connected), `cancel()`, `is_running()`, `last_read()`, `run_tick( $budget = null )` and `progress()` (the keys `assets/js/admin.js` polls);
  - `report()`: `v`, `read`, `started`, `totals` (table => rows read), `no_email`, `groups` (key => `classify()`'s answer plus `refs`, `email` and `last`, Ready first and the most recent first in each) and `counts` (`addresses`, `ready`, `decide`, and table => n under `candidates` and `held`), or an empty array;
  - `context()` (`live` and `work_columns`), `refs_for( array $ids )` (record ID => each reference's `kind`, `key`, `object` and, for a post, `status`) and `drop_deleted( array $gone )` (each `key`, `table` and `id`), which takes deleted rows out of the stored report at once.

- [ ] **Step 1: Write the failing checks.** Create `bin/test-duplicates-scan.php`:

```php
<?php
/**
 * The Student Duplicate Finder's scan: three tables read every three hours, duplicates kept.
 *
 * What each block pins, and why:
 *
 * - **It runs where the four syncs do not.** The shared three-hour recurrence, registered before
 *   the event is placed (an event on a recurrence WordPress cannot name is dropped without a word),
 *   150 minutes into the cycle.
 * - A run reads Students, Students Reports and Feedback page by page and keeps **only** the
 *   addresses with more than one row in some table, each classified by the real rules. A row with
 *   no address is counted and left out: it cannot be grouped.
 * - What the site points at is read by value from user and post meta, so a row a site account uses
 *   is locked; the finder's own copies are not a reason to keep a row.
 * - **A failed or an empty read ends the run and keeps the last good list** (spec decision 3.4),
 *   instead of the sync behaviour of staying "running" with no next tick.
 * - Cancel keeps the list too, and a deletion takes its rows out of the stored list at once.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicates-scan.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['opts']       = array();
$GLOBALS['cron']       = array();
$GLOBALS['recurrence'] = array();
$GLOBALS['pages']      = array();
$GLOBALS['fail_page']  = array();
$GLOBALS['usermeta']   = array();
$GLOBALS['postmeta']   = array();
$GLOBALS['connected']  = true;
$GLOBALS['interval']   = false;

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) { $GLOBALS['actions'][ $hook ] = $callback; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $a; return true; }
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_get_schedules() {
	$schedules = array( 'daily' => array( 'interval' => DAY_IN_SECONDS ) );
	if ( $GLOBALS['interval'] ) {
		$schedules['wpcpm_three_hours'] = array( 'interval' => 3 * HOUR_IN_SECONDS );
	}
	return $schedules;
}
function wp_schedule_event( $ts, $recurrence, $hook ) {
	if ( ! isset( wp_get_schedules()[ $recurrence ] ) ) {
		return false;
	}
	$GLOBALS['cron'][ $hook ]       = $ts;
	$GLOBALS['recurrence'][ $hook ] = $recurrence;
	return true;
}
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ], $GLOBALS['recurrence'][ $hook ] ); return 0; }
function wp_get_scheduled_event( $hook ) {
	if ( ! isset( $GLOBALS['cron'][ $hook ] ) ) {
		return false;
	}
	return (object) array(
		'hook'      => $hook,
		'timestamp' => $GLOBALS['cron'][ $hook ],
		'schedule'  => isset( $GLOBALS['recurrence'][ $hook ] ) ? $GLOBALS['recurrence'][ $hook ] : false,
	);
}

/* ---- the other pieces, stubbed to their contracts ----------------------- */

/** The client: pages handed out from $GLOBALS['pages'][ table ], the offset being the page index. */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function fetch_page( $table, array $args = array() ) {
		$GLOBALS['fetched'][] = $table;
		$index = empty( $args['offset'] ) ? 0 : (int) $args['offset'];
		if ( isset( $GLOBALS['fail_page'][ $table ] ) && $GLOBALS['fail_page'][ $table ] === $index ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 503): no further detail' );
		}
		$pages = isset( $GLOBALS['pages'][ $table ] ) ? $GLOBALS['pages'][ $table ] : array();
		return array(
			'records' => isset( $pages[ $index ] ) ? $pages[ $index ] : array(),
			'offset'  => isset( $pages[ $index + 1 ] ) ? (string) ( $index + 1 ) : null,
		);
	}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK' ); }
	public static function is_connected() { return $GLOBALS['connected']; }
}
/** The three-hour recurrence is the students sync's; this stand-in only records that it was asked for. */
class WPCPM_Students_Sync {
	const EVERY_THREE_HOURS = 'wpcpm_three_hours';
	public static function register_interval() { $GLOBALS['interval'] = true; }
	public static function cycle_start() { return 1789000000; }
}
class WPCPM_Mentors_Sync {
	public static function tracked_statuses( $settings = null ) {
		$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
		return array( 'active' => $active, 'past' => array( 'Graduate', 'Dropped out' ), 'all' => $active );
	}
}
class WPCPM_Program {
	public static function labels() { return array( 'In Sensei' => '150h', 'Developer Track' => 'Developer Track' ); }
	public static function track( $status ) { return 'In Sensei' === $status ? '150h' : 'dev'; }
}
class WPCPM_Student_Report_Form {
	public static function fields( $track ) {
		return '150h' === $track
			? array( 'Beginner WordPress User - final grade' => array(), 'Post Reflection: Building Your Personal Website' => array() )
			: array( 'Beginner WordPress Developer' => array(), 'Post Reflection: Building Your Personal Website' => array() );
	}
}

/** Reads by value from user meta and post meta, the two queries `refs_for()` makes. */
class Test_WPDB {
	public $usermeta = 'wp_usermeta', $postmeta = 'wp_postmeta', $posts = 'wp_posts';
	public function prepare( $sql, $args ) { return array( $sql, (array) $args ); }
	public function get_results( $prepared, $output = null ) {
		list( $sql, $ids ) = $prepared;
		$rows = array();
		$from = false !== strpos( $sql, 'wp_usermeta' ) ? $GLOBALS['usermeta'] : $GLOBALS['postmeta'];
		foreach ( $from as $row ) {
			if ( in_array( $row['meta_value'], $ids, true ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new Test_WPDB();

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * A record ID that says what it is.
 *
 * @param string $tag Up to fourteen letters and digits.
 * @return string
 */
function rid( $tag ) {
	return 'rec' . str_pad( strtoupper( $tag ), 14, '0' );
}

/**
 * A record in the shape the API returns.
 *
 * @param string $tag     Record ID tag.
 * @param string $created ISO 8601.
 * @param array  $fields  Cells.
 * @return array
 */
function record( $tag, $created, array $fields ) {
	return array(
		'id'          => rid( $tag ),
		'createdTime' => $created,
		'fields'      => $fields,
	);
}

/** Run ticks until the scan stops, the way the screen's poll does. */
function run_to_end() {
	for ( $i = 0; $i < 50 && WPCPM_Duplicates_Scan::is_running(); $i++ ) {
		WPCPM_Duplicates_Scan::run_tick( WPCPM_Duplicates_Scan::BUDGET_AJAX );
	}
}

/**
 * The base the scan reads: one student applied twice (every table twice), one student once, a
 * Students row with no address, and the same address spelled once in capitals.
 */
function base() {
	$GLOBALS['pages'] = array(
		'tblSTUDENTS' => array(
			array(
				record( 'stuold', '2026-01-27T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
				record( 'stuonce', '2026-03-01T10:00:00.000Z', array( 'Email' => 'once@example.test', 'Full Name' => 'Once Only', 'Status' => 'In Sensei' ) ),
			),
			array(
				record( 'stunomail', '2026-03-02T10:00:00.000Z', array( 'Full Name' => 'No Address', 'Status' => 'Interested' ) ),
			),
			array(
				record( 'stunew', '2026-09-09T10:00:00.000Z', array( 'Email' => 'Student@Example.test', 'Full Name' => 'A Student', 'Status' => 'In Sensei' ) ),
			),
		),
		'tblREPORTS'  => array(
			array(
				record( 'repold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
				record( 'reponce', '2026-03-01T10:00:00.000Z', array( 'Email' => 'once@example.test', 'Name' => 'Once Only', 'Status' => 'In Sensei', 'Beginner WordPress User - final grade' => 90 ) ),
			),
			array(
				record( 'repnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'In Sensei' ) ),
			),
		),
		'tblFEEDBACK' => array(
			array(
				record( 'fbold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student' ) ),
				record( 'fbnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Course' => 'In Sensei' ) ),
				record( 'fbonce', '2026-03-01T10:00:00.000Z', array( 'Email' => 'once@example.test', 'Name' => 'Once Only', 'Course' => 'In Sensei' ) ),
			),
		),
	);
}

$key = WPCPM_Duplicate_Rules::key( 'student@example.test' );

/* ---- the schedule ------------------------------------------------------- */

echo "\n=== The schedule ===\n";

WPCPM_Duplicates_Scan::register_cron();
ck( 'the recurring run is on the shared three-hour recurrence, registered before it was placed', array( $GLOBALS['interval'], $GLOBALS['recurrence'][ WPCPM_Duplicates_Scan::CRON_SCAN ] ), array( true, 'wpcpm_three_hours' ) );
ck( '150 minutes into the cycle, inside it', array( $GLOBALS['cron'][ WPCPM_Duplicates_Scan::CRON_SCAN ] - 1789000000, WPCPM_Duplicates_Scan::SCHEDULE_OFFSET_MINUTES < 180 ), array( 150 * 60, true ) );
ck( 'both events hooked', array( isset( $GLOBALS['actions'][ WPCPM_Duplicates_Scan::CRON_SCAN ] ), isset( $GLOBALS['actions'][ WPCPM_Duplicates_Scan::CRON_TICK ] ) ), array( true, true ) );

$GLOBALS['cron'][ WPCPM_Duplicates_Scan::CRON_SCAN ]       = 123;
$GLOBALS['recurrence'][ WPCPM_Duplicates_Scan::CRON_SCAN ] = 'daily';
WPCPM_Duplicates_Scan::schedule();
ck( 'an event on another recurrence is moved onto this one', $GLOBALS['recurrence'][ WPCPM_Duplicates_Scan::CRON_SCAN ], 'wpcpm_three_hours' );

/* ---- starting ----------------------------------------------------------- */

echo "\n=== Starting ===\n";

$GLOBALS['connected'] = false;
ck( 'without an Airtable connection it will not start, and says why', array( WPCPM_Duplicates_Scan::start()->get_error_code(), WPCPM_Duplicates_Scan::is_running(), '' !== get_option( WPCPM_Duplicates_Scan::OPT_ERROR, '' ) ), array( 'wpcpm_not_connected', false, true ) );
$GLOBALS['connected'] = true;

base();
ck( 'a run starts on Students', array( WPCPM_Duplicates_Scan::start(), WPCPM_Duplicates_Scan::is_running(), get_option( WPCPM_Duplicates_Scan::OPT_STATE )['phase'] ), array( true, true, 'students' ) );
$state = get_option( WPCPM_Duplicates_Scan::OPT_STATE );
ck( 'with the context read once: the active statuses, and every track\'s report-form columns', array( $state['context']['live'][0], $state['context']['work_columns'] ), array( 'In Sensei', array( 'Beginner WordPress User - final grade', 'Post Reflection: Building Your Personal Website', 'Beginner WordPress Developer' ) ) );
ck( 'the working state is not autoloaded', $GLOBALS['autoload'][ WPCPM_Duplicates_Scan::OPT_STATE ], false );
ck( 'and the next tick is on the clock', false !== wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ), true );

/* ---- a run -------------------------------------------------------------- */

echo "\n=== A run ===\n";

$GLOBALS['usermeta'] = array( array( 'user_id' => 9, 'meta_key' => 'wpcpm_student_record_id', 'meta_value' => rid( 'repnew' ) ) );
$GLOBALS['postmeta'] = array(
	array( 'ID' => 501, 'post_type' => 'wpcpm_mentor_note', 'post_status' => 'private', 'meta_key' => '_wpcpm_student_record', 'meta_value' => rid( 'repnew' ) ),
	array( 'ID' => 502, 'post_type' => 'wpcpm_dup_copy', 'post_status' => 'private', 'meta_key' => '_wpcpm_dup_record', 'meta_value' => rid( 'fbold' ) ),
);

run_to_end();
$report = WPCPM_Duplicates_Scan::report();

ck( 'it read the three tables, page by page', $GLOBALS['fetched'], array( 'tblSTUDENTS', 'tblSTUDENTS', 'tblSTUDENTS', 'tblREPORTS', 'tblREPORTS', 'tblFEEDBACK' ) );
ck( 'and finished: no working state, no lock, no tick, the time recorded', array( WPCPM_Duplicates_Scan::is_running(), get_option( WPCPM_Duplicates_Scan::OPT_LOCK, 'none' ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ), WPCPM_Duplicates_Scan::last_read() > 0 ), array( false, 'none', false, true ) );
ck( 'the report counts every row read, and the one with no address', array( $report['totals'], $report['no_email'] ), array( array( 'students' => 4, 'reports' => 3, 'feedback' => 3 ), 1 ) );
ck( 'it keeps only the address with more than one row, whatever its capitals', array_keys( $report['groups'] ), array( $key ) );
ck( 'the student is headed with a name and an address', array( $report['groups'][ $key ]['name'], strtolower( $report['groups'][ $key ]['email'] ) ), array( 'A Student', 'student@example.test' ) );
ck( 'and flagged for the two spellings', $report['groups'][ $key ]['flags'], array( 'spelling' ) );

$by_id = array();
foreach ( $report['groups'][ $key ]['rows'] as $rows ) {
	foreach ( $rows as $row ) {
		$by_id[ $row['id'] ] = $row;
	}
}
ck( 'each row carries the rules\' proposal', array( $by_id[ rid( 'stuold' ) ]['proposal'], $by_id[ rid( 'repold' ) ]['proposal'], $by_id[ rid( 'fbold' ) ]['proposal'] ), array( 'delete', 'delete', 'delete' ) );
ck( 'the report row a site account and a mentor note point at is locked', array( $by_id[ rid( 'repnew' ) ]['locked'], count( $report['groups'][ $key ]['refs'][ rid( 'repnew' ) ] ) ), array( true, 2 ) );
ck( 'the finder\'s own copy of a row is no reason to keep it', array( $by_id[ rid( 'fbold' ) ]['locked'], isset( $report['groups'][ $key ]['refs'][ rid( 'fbold' ) ] ) ), array( false, false ) );
ck( 'the counts the tiles read', $report['counts'], array( 'addresses' => 1, 'ready' => 1, 'decide' => 0, 'candidates' => array( 'students' => 1, 'reports' => 1, 'feedback' => 1 ), 'held' => array( 'students' => 0, 'reports' => 0, 'feedback' => 0 ) ) );
ck( 'the report is not autoloaded', $GLOBALS['autoload'][ WPCPM_Duplicates_Scan::OPT_REPORT ], false );

/* ---- failing closed ------------------------------------------------------- */

echo "\n=== A failed or an empty read ends the run and keeps the last list ===\n";

$good               = $report;
$GLOBALS['fetched'] = array();
$GLOBALS['fail_page'] = array( 'tblREPORTS' => 1 );
WPCPM_Duplicates_Scan::start();
run_to_end();
ck( 'a page Airtable refused ends the run: not running, no state, no tick', array( WPCPM_Duplicates_Scan::is_running(), get_option( WPCPM_Duplicates_Scan::OPT_STATE, 'none' ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ) ), array( false, 'none', false ) );
ck( 'the error is kept for the screen', get_option( WPCPM_Duplicates_Scan::OPT_ERROR ), 'Airtable request failed (HTTP 503): no further detail' );
ck( 'and the last good list stands', WPCPM_Duplicates_Scan::report(), $good );
ck( 'so the next run can start', WPCPM_Duplicates_Scan::start(), true );
$GLOBALS['fail_page'] = array();
WPCPM_Duplicates_Scan::cancel();

$GLOBALS['pages']['tblFEEDBACK'] = array( array() );
WPCPM_Duplicates_Scan::start();
run_to_end();
ck( 'a table that answers with no rows at all is an error, not a base without students', get_option( WPCPM_Duplicates_Scan::OPT_ERROR ), 'Airtable returned no rows for feedback_table, so the last list is kept.' );
ck( 'and the last good list stands', WPCPM_Duplicates_Scan::report(), $good );
base();

WPCPM_Duplicates_Scan::start();
WPCPM_Duplicates_Scan::cancel();
ck( 'Cancel stops the run and keeps the list', array( WPCPM_Duplicates_Scan::is_running(), WPCPM_Duplicates_Scan::report() === $good ), array( false, true ) );

/* ---- the lock and progress ------------------------------------------------ */

echo "\n=== The lock, and what the progress bar reads ===\n";

WPCPM_Duplicates_Scan::start();
$GLOBALS['opts'][ WPCPM_Duplicates_Scan::OPT_LOCK ] = time();
WPCPM_Duplicates_Scan::run_tick( 1 );
ck( 'a tick another request holds the lock for does nothing', get_option( WPCPM_Duplicates_Scan::OPT_STATE )['phase'], 'students' );
$progress = WPCPM_Duplicates_Scan::progress();
ck( 'the progress carries every key assets/js/admin.js reads', array_keys( $progress ), array( 'running', 'phase', 'label', 'detail', 'percent', 'step', 'step_total', 'step_label', 'stats', 'elapsed', 'idle', 'error', 'stalled' ) );
ck( 'the first phase, as the bar words it', array( $progress['label'], $progress['step_label'], $progress['percent'] ), array( 'Reading Students…', 'Step 1 of 4', 0 ) );
$GLOBALS['opts'][ WPCPM_Duplicates_Scan::OPT_LOCK ] = time() - WPCPM_Duplicates_Scan::LOCK_TIMEOUT - 5;
run_to_end();
ck( 'a stale lock is taken over and the run finishes', array( WPCPM_Duplicates_Scan::is_running(), array_keys( WPCPM_Duplicates_Scan::report()['groups'] ) ), array( false, array( $key ) ) );

/* ---- a delete, reflected at once ------------------------------------------ */

echo "\n=== Deleted rows leave the stored list at once ===\n";

WPCPM_Duplicates_Scan::drop_deleted( array( array( 'key' => $key, 'table' => 'feedback', 'id' => rid( 'fbold' ) ) ) );
$after = WPCPM_Duplicates_Scan::report();
ck( 'one row gone: the student stays, with two tables still doubled', array( array_keys( $after['groups'] ), $after['groups'][ $key ]['counts'], $after['totals']['feedback'] ), array( array( $key ), array( 'students' => 2, 'reports' => 2, 'feedback' => 1 ), 2 ) );

WPCPM_Duplicates_Scan::drop_deleted(
	array(
		array( 'key' => $key, 'table' => 'students', 'id' => rid( 'stuold' ) ),
		array( 'key' => $key, 'table' => 'reports', 'id' => rid( 'repold' ) ),
	)
);
$after = WPCPM_Duplicates_Scan::report();
ck( 'the rest gone: the student leaves the list and the counts say so', array( $after['groups'], $after['counts']['addresses'], $after['counts']['ready'] ), array( array(), 0, 0 ) );

/* ---- uninstall ------------------------------------------------------------ */

echo "\n=== Uninstall ===\n";

WPCPM_Duplicates_Scan::uninstall();
ck( 'uninstall takes the list, the times, the error and both events', array( get_option( WPCPM_Duplicates_Scan::OPT_REPORT, 'none' ), get_option( WPCPM_Duplicates_Scan::OPT_LAST, 'none' ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_SCAN ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ) ), array( 'none', 'none', false, false ) );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php' );
ck( 'no dash but the plain hyphen in the class', 1 === preg_match( '/\x{2013}|\x{2014}/u', $source ), false );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

and apply:

```diff
--- a/bin/test-sponsors-sync.php
+++ b/bin/test-sponsors-sync.php
@@ -797,16 +797,21 @@ ck( 'the offset is read from the class rather than copied', WPCPM_Sponsors_Sync:
 // the four classes is loaded for real. Two syncs sharing a minute is the failure the offsets
 // exist to prevent, and it would show up nowhere else - each sync's own section only ever
 // compares itself with the students run.
+// The Student Duplicate Finder's scan runs on the same recurrence (1.102.0), so it takes a fifth
+// minute of its own. Loaded here for its constant alone.
+require_once dirname( __DIR__ ) . '/includes/tools/class-wpcpm-duplicates-scan.php';
+
 $offsets = array(
 	WPCPM_Students_Sync::SCHEDULE_OFFSET_MINUTES,
 	WPCPM_Mentors_Sync::SCHEDULE_OFFSET_MINUTES,
 	WPCPM_Institutions_Sync::SCHEDULE_OFFSET_MINUTES,
 	WPCPM_Sponsors_Sync::SCHEDULE_OFFSET_MINUTES,
+	WPCPM_Duplicates_Scan::SCHEDULE_OFFSET_MINUTES,
 );
 
-ck( 'and the four syncs take four distinct minutes inside one three-hour cycle',
+ck( 'and the four syncs and the duplicate scan take five distinct minutes inside one three-hour cycle',
 	array( count( array_unique( $offsets ) ), max( $offsets ) < 180 ),
-	array( 4, true ) );
+	array( 5, true ) );
 
 $placed = $GLOBALS['cron'][ WPCPM_Sponsors_Sync::CRON_DAILY ];
 WPCPM_Sponsors_Sync::schedule();
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-duplicates-scan.php`, then `php bin/test-sponsors-sync.php`. Expected:

```text
PHP Warning:  require_once(includes/tools/class-wpcpm-duplicates-scan.php): Failed to open stream: No such file or directory in bin/test-duplicates-scan.php on line 161
PHP Fatal error:  Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-duplicates-scan.php' (include_path='...') in bin/test-duplicates-scan.php:161
```

```text
PHP Warning:  require_once(includes/tools/class-wpcpm-duplicates-scan.php): Failed to open stream: No such file or directory in bin/test-sponsors-sync.php on line 802
PHP Fatal error:  Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-duplicates-scan.php' (include_path='...') in bin/test-sponsors-sync.php:802
```

- [ ] **Step 3: Write the class.** Create `includes/tools/class-wpcpm-duplicates-scan.php`:

```php
<?php
/**
 * Student Duplicate Finder: the scan.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads Students, Students Reports and Feedback every three hours and keeps the addresses that
 * have more than one row in any of them, with a proposal for every row.
 *
 * Built like the four Airtable syncs, `WPCPM_Sponsors_Sync` above all: a resumable state machine in
 * an option, ticks of a budgeted slice under a lock, the shared three-hour recurrence at an offset
 * of its own, and the progress payload `assets/js/admin.js` polls. It departs from them in one place
 * on purpose: **a failed read ends the run.** A sync that stops on an error keeps its state and
 * stays "running", so the recurring event skips every later run until somebody cancels; this one
 * records the error, drops the working state and keeps the last good report, and the next run
 * starts clean three hours later (spec decision 3.4).
 *
 * It writes nothing to Airtable, and the report it keeps holds duplicates only (decision 3.5).
 */
final class WPCPM_Duplicates_Scan {

	/** The recurring run. */
	const CRON_SCAN = 'wpcpm_duplicates_scan';

	/** The single event that carries a run on between ticks. */
	const CRON_TICK = 'wpcpm_duplicates_tick';

	const OPT_STATE  = 'wpcpm_duplicates_state';
	const OPT_REPORT = 'wpcpm_duplicates_report';
	const OPT_LAST   = 'wpcpm_duplicates_last_scan';
	const OPT_ERROR  = 'wpcpm_duplicates_last_error';
	const OPT_LOCK   = 'wpcpm_duplicates_lock';

	const BUDGET       = 18;
	const BUDGET_AJAX  = 8;
	const LOCK_TIMEOUT = 120;

	/**
	 * Minutes into the three-hour cycle the scan runs at.
	 *
	 * After the students (30), mentors (60), institutions (90) and sponsors (120) syncs, so that no
	 * two of the five share a WP-Cron request. Minutes, and not a constant expression on
	 * `MINUTE_IN_SECONDS`, for the reason `WPCPM_Sponsors_Sync::SCHEDULE_OFFSET_MINUTES` gives.
	 */
	const SCHEDULE_OFFSET_MINUTES = 150;

	/** The report's shape. A stored report of any other version is not read. */
	const REPORT_VERSION = 1;

	/** The setting that names each table. */
	const TABLE_SETTINGS = array(
		'students' => 'students_table',
		'reports'  => 'reports_table',
		'feedback' => 'feedback_table',
	);

	/**
	 * Post types whose meta is the finder's own, and never a reason to keep a row.
	 *
	 * The copies `WPCPM_Duplicate_Vault` keeps carry the record ID they are a copy of. A copy whose
	 * delete Airtable never confirmed is of a row that may still be there, and counting the copy as
	 * the site pointing at that row would lock it against the very delete it was made for. Spelled
	 * out rather than read from the vault's constant, so this class loads without it.
	 */
	const OWN_POST_TYPES = array( 'wpcpm_dup_copy' );

	/**
	 * The phases, their progress-bar weights and the pages each is expected to take.
	 *
	 * Eleven pages a table: a hundred rows a page and a little over a thousand rows in each on
	 * 10 September 2026. The count only shapes the bar; a longer table simply fills its share later.
	 *
	 * @return array<string, array{label: string, weight: int, steps: int}>
	 */
	public static function phases() {
		return array(
			'students' => array(
				'label'  => __( 'Reading Students', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 11,
			),
			'reports'  => array(
				'label'  => __( 'Reading Students Reports', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 11,
			),
			'feedback' => array(
				'label'  => __( 'Reading Feedback', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 11,
			),
			'finish'   => array(
				'label'  => __( 'Finding duplicates', 'wpcredits-program-manager' ),
				'weight' => 10,
				'steps'  => 1,
			),
		);
	}

	/**
	 * Hook the two cron events and make sure the recurring one is on the clock.
	 */
	public static function register_cron() {
		add_action( self::CRON_SCAN, array( __CLASS__, 'cron_scan' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'run_tick' ) );

		self::schedule();
	}

	/**
	 * Ensure the recurring event exists, on the three-hour recurrence, at this scan's offset.
	 *
	 * The recurrence is checked and not only the event's existence, as `WPCPM_Sponsors_Sync` does,
	 * and the interval is the students sync's, never one of this class's own: an event on a schedule
	 * WordPress cannot find is dropped without a word.
	 */
	public static function schedule() {
		WPCPM_Students_Sync::register_interval();

		$event = wp_get_scheduled_event( self::CRON_SCAN );

		if ( $event && isset( $event->schedule ) && WPCPM_Students_Sync::EVERY_THREE_HOURS === $event->schedule ) {
			return;
		}

		if ( $event ) {
			wp_clear_scheduled_hook( self::CRON_SCAN );
		}

		wp_schedule_event(
			WPCPM_Students_Sync::cycle_start() + ( self::SCHEDULE_OFFSET_MINUTES * MINUTE_IN_SECONDS ),
			WPCPM_Students_Sync::EVERY_THREE_HOURS,
			self::CRON_SCAN
		);
	}

	/**
	 * Activation: the schedule.
	 */
	public static function activate() {
		self::schedule();
	}

	/**
	 * Deactivation: both events off the clock, the working state gone. The report stays.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_SCAN );
		wp_clear_scheduled_hook( self::CRON_TICK );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
	}

	/**
	 * Uninstall: everything the scan keeps, the report of duplicated students included.
	 */
	public static function uninstall() {
		self::deactivate();

		foreach ( array( self::OPT_REPORT, self::OPT_LAST, self::OPT_ERROR ) as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * The recurring run.
	 */
	public static function cron_scan() {
		if ( self::is_running() ) {
			return;
		}

		self::start();
	}

	/**
	 * Begin a run.
	 *
	 * @param bool $run_first_tick Process one slice before returning, for WP-CLI.
	 * @return true|WP_Error
	 */
	public static function start( $run_first_tick = false ) {
		if ( ! WPCPM_Settings::is_connected() ) {
			$error = new WP_Error( 'wpcpm_not_connected', __( 'Add an Airtable Personal Access Token and Base ID before scanning.', 'wpcredits-program-manager' ) );
			update_option( self::OPT_ERROR, $error->get_error_message(), false );

			return $error;
		}

		delete_option( self::OPT_LOCK );

		update_option(
			self::OPT_STATE,
			array(
				'phase'   => 'students',
				'offset'  => null,
				'started' => time(),
				'touched' => time(),
				'steps'   => array(),
				// Read once, so one run classifies every row against the same statuses and columns.
				'context' => self::context(),
				// Address key => table => reduced rows, as the pages arrive.
				'rows'    => array(),
				'stats'   => self::empty_stats(),
			),
			false
		);

		delete_option( self::OPT_ERROR );

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 10, self::CRON_TICK );
		}

		if ( $run_first_tick ) {
			self::run_tick();
		}

		return true;
	}

	/**
	 * Stop a run. The last good report stays.
	 */
	public static function cancel() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Whether a run is in progress.
	 *
	 * @return bool
	 */
	public static function is_running() {
		$state = get_option( self::OPT_STATE );

		return is_array( $state ) && ! empty( $state['phase'] ) && 'done' !== $state['phase'];
	}

	/**
	 * When the last run finished, unix time, or 0.
	 *
	 * @return int
	 */
	public static function last_read() {
		return (int) get_option( self::OPT_LAST, 0 );
	}

	/**
	 * The last good report, or an empty array when there is none of this version.
	 *
	 * @return array
	 */
	public static function report() {
		$report = get_option( self::OPT_REPORT );

		return ( is_array( $report ) && isset( $report['v'] ) && self::REPORT_VERSION === (int) $report['v'] ) ? $report : array();
	}

	/**
	 * Process one slice of work.
	 *
	 * @param int|null $budget Seconds of work to attempt.
	 */
	public static function run_tick( $budget = null ) {
		$state = get_option( self::OPT_STATE );

		if ( ! is_array( $state ) || empty( $state['phase'] ) || 'done' === $state['phase'] ) {
			return;
		}

		if ( ! self::acquire_lock() ) {
			return;
		}

		$budget   = ( null === $budget ) ? self::BUDGET : max( 1, (int) $budget );
		$deadline = microtime( true ) + $budget;
		$airtable = new WPCPM_Airtable();
		$settings = WPCPM_Settings::get();

		while ( microtime( true ) < $deadline && 'done' !== $state['phase'] ) {
			$before = $state['phase'];

			if ( 'finish' === $state['phase'] ) {
				$result = self::phase_finish( $state );
			} elseif ( isset( self::TABLE_SETTINGS[ $state['phase'] ] ) ) {
				$result = self::phase_read( $state, $airtable, $settings );
			} else {
				$state['phase'] = 'done';
				$result         = true;
			}

			if ( ! isset( $state['steps'][ $before ] ) ) {
				$state['steps'][ $before ] = 0;
			}
			++$state['steps'][ $before ];
			$state['touched'] = time();

			if ( is_wp_error( $result ) ) {
				self::fail( $result );

				return;
			}

			update_option( self::OPT_STATE, $state, false );
		}

		self::release_lock();

		if ( 'done' === $state['phase'] ) {
			self::finish();

			return;
		}

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_TICK );
		}
	}

	/**
	 * Progress for the screen and the AJAX poll: the keys assets/js/admin.js reads.
	 *
	 * @return array
	 */
	public static function progress() {
		$state   = get_option( self::OPT_STATE );
		$state   = is_array( $state ) ? $state : array();
		$phase   = isset( $state['phase'] ) ? (string) $state['phase'] : '';
		$phases  = self::phases();
		$stats   = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : self::empty_stats();
		$running = self::is_running();

		$order   = array_keys( $phases );
		$index   = array_search( $phase, $order, true );
		$index   = ( false === $index ) ? 0 : (int) $index;
		$started = isset( $state['started'] ) ? (int) $state['started'] : 0;
		$touched = isset( $state['touched'] ) ? (int) $state['touched'] : $started;

		return array(
			'running'    => $running,
			'phase'      => $phase,
			'label'      => isset( $phases[ $phase ]['label'] ) ? $phases[ $phase ]['label'] . '…' : '',
			'detail'     => self::phase_detail( $phase, $stats ),
			'percent'    => self::percent( $state ),
			'step'       => $running ? $index + 1 : count( $order ),
			'step_total' => count( $order ),
			/* translators: 1: current phase number, 2: total number of phases. */
			'step_label' => $running ? sprintf( __( 'Step %1$d of %2$d', 'wpcredits-program-manager' ), $index + 1, count( $order ) ) : '',
			'stats'      => $stats,
			'elapsed'    => $started ? max( 0, time() - $started ) : 0,
			'idle'       => $touched ? max( 0, time() - $touched ) : 0,
			'error'      => (string) get_option( self::OPT_ERROR, '' ),
			'stalled'    => $running && $touched && ( time() - $touched ) > self::LOCK_TIMEOUT,
		);
	}

	/**
	 * A zeroed statistics array.
	 *
	 * @return array<string, int>
	 */
	public static function empty_stats() {
		return array(
			'students_seen' => 0,
			'reports_seen'  => 0,
			'feedback_seen' => 0,
			'no_email'      => 0,
			'addresses'     => 0,
		);
	}

	/**
	 * What the rules are asked against, read from the site: the live statuses and the work columns.
	 *
	 * The live statuses are the active list of the `student_statuses` setting, the one both syncs
	 * fetch by. The work columns are every column the Student Report Card's report form writes for
	 * any track the program map knows, the four built-in tracks and every Track Builder track: the
	 * map is `WPCPM_Program::labels()`, and `fields()` takes the key `track()` gives each status.
	 *
	 * @return array `live` (string[]) and `work_columns` (string[]).
	 */
	public static function context() {
		$statuses = WPCPM_Mentors_Sync::tracked_statuses();
		$work     = array();

		foreach ( array_keys( WPCPM_Program::labels() ) as $status ) {
			foreach ( array_keys( WPCPM_Student_Report_Form::fields( WPCPM_Program::track( $status ) ) ) as $column ) {
				$work[ (string) $column ] = true;
			}
		}

		return array(
			'live'         => $statuses['active'],
			'work_columns' => array_keys( $work ),
		);
	}

	/**
	 * Everything on this site whose meta value is exactly one of these record IDs.
	 *
	 * User meta and post meta both, matched on the value rather than on a list of keys, so a key a
	 * later release adds is covered the day it lands. The finder's own copies are left out
	 * (`OWN_POST_TYPES`). Asked twice: by the scan for the list, and by the delete handler just
	 * before it deletes, because a note written in between is exactly what must stop a delete.
	 *
	 * @param string[] $ids Record IDs.
	 * @return array<string, array[]> Record ID => each `kind` (`user` or a post type), `key`,
	 *                               `object` (the user or post ID) and, for a post, `status`.
	 */
	public static function refs_for( array $ids ) {
		global $wpdb;

		$ids  = array_values( array_unique( array_filter( array_map( 'strval', $ids ), array( 'WPCPM_Airtable', 'is_record_id' ) ) ) );
		$refs = array();

		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per ID, built above; a read that must be fresh.
			$users = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_value IN ($in)", $chunk ), ARRAY_A );

			foreach ( (array) $users as $row ) {
				$refs[ (string) $row['meta_value'] ][] = array(
					'kind'   => 'user',
					'key'    => (string) $row['meta_key'],
					'object' => (int) $row['user_id'],
				);
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- As above.
			$posts = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_type, p.post_status, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_value IN ($in)", $chunk ), ARRAY_A );

			foreach ( (array) $posts as $row ) {
				if ( in_array( (string) $row['post_type'], self::OWN_POST_TYPES, true ) ) {
					continue;
				}

				$refs[ (string) $row['meta_value'] ][] = array(
					'kind'   => (string) $row['post_type'],
					'key'    => (string) $row['meta_key'],
					'object' => (int) $row['ID'],
					'status' => (string) $row['post_status'],
				);
			}
		}

		return $refs;
	}

	/**
	 * Take rows Airtable confirmed deleted out of the stored report, at once.
	 *
	 * The list and the dashboard count are then right without waiting three hours for the next scan
	 * (spec 7.3 step 9). Each touched student is classified again on what is left; one with no
	 * table holding more than a single row is no longer a duplicate and leaves the report.
	 *
	 * @param array $gone Each `key`, `table` and `id`.
	 */
	public static function drop_deleted( array $gone ) {
		$report = self::report();

		if ( ! $report ) {
			return;
		}

		$context = self::context();
		$touched = array();

		foreach ( $gone as $one ) {
			$key   = (string) $one['key'];
			$table = (string) $one['table'];
			$id    = (string) $one['id'];

			if ( ! isset( $report['groups'][ $key ]['rows'][ $table ] ) ) {
				continue;
			}

			$before = count( $report['groups'][ $key ]['rows'][ $table ] );

			$report['groups'][ $key ]['rows'][ $table ] = array_values(
				array_filter(
					$report['groups'][ $key ]['rows'][ $table ],
					static function ( $row ) use ( $id ) {
						return $row['id'] !== $id;
					}
				)
			);

			if ( count( $report['groups'][ $key ]['rows'][ $table ] ) < $before && isset( $report['totals'][ $table ] ) ) {
				--$report['totals'][ $table ];
			}

			unset( $report['groups'][ $key ]['refs'][ $id ] );
			$touched[ $key ] = true;
		}

		foreach ( array_keys( $touched ) as $key ) {
			$tables = $report['groups'][ $key ]['rows'];
			$still  = false;

			foreach ( $tables as $rows ) {
				if ( count( $rows ) > 1 ) {
					$still = true;
					break;
				}
			}

			if ( ! $still ) {
				unset( $report['groups'][ $key ] );
				continue;
			}

			$report['groups'][ $key ] = self::group( $tables, $report['groups'][ $key ]['refs'], $context );
		}

		$report['groups'] = self::sorted( $report['groups'] );
		$report['counts'] = self::counts( $report['groups'] );

		update_option( self::OPT_REPORT, $report, false );
	}

	/**
	 * Read one page of the table the run is on.
	 *
	 * @param array          $state    Scan state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_read( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$table  = (string) $state['phase'];
		$source = isset( $settings[ self::TABLE_SETTINGS[ $table ] ] ) ? (string) $settings[ self::TABLE_SETTINGS[ $table ] ] : '';
		$page   = $airtable->fetch_page( $source, array( 'offset' => $state['offset'] ) );

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$row = WPCPM_Duplicate_Rules::reduce( $table, $record, $state['context'] );
			$key = WPCPM_Duplicate_Rules::key( $row['email'] );

			++$state['stats'][ $table . '_seen' ];

			if ( '' === $key || '' === $row['id'] ) {
				++$state['stats']['no_email'];
				continue;
			}

			$state['rows'][ $key ][ $table ][] = $row;
		}

		$state['offset'] = empty( $page['offset'] ) ? null : (string) $page['offset'];

		if ( null !== $state['offset'] ) {
			return true;
		}

		// A table that answers with no rows at all is a renamed table or a revoked token, not a
		// base without students, and a report built on it would say nobody is duplicated.
		if ( 0 === (int) $state['stats'][ $table . '_seen' ] ) {
			return new WP_Error(
				'wpcpm_duplicates_empty',
				/* translators: %s: table setting name, for example students_table. */
				sprintf( __( 'Airtable returned no rows for %s, so the last list is kept.', 'wpcredits-program-manager' ), self::TABLE_SETTINGS[ $table ] )
			);
		}

		$order          = array_keys( self::phases() );
		$state['phase'] = $order[ (int) array_search( $table, $order, true ) + 1 ];

		return true;
	}

	/**
	 * Keep the duplicated addresses, look up what the site points at, classify, and store.
	 *
	 * @param array $state Scan state, by reference.
	 * @return true
	 */
	private static function phase_finish( array &$state ) {
		$dupes = array();
		$ids   = array();

		foreach ( $state['rows'] as $key => $tables ) {
			foreach ( $tables as $rows ) {
				if ( count( $rows ) > 1 ) {
					$dupes[ $key ] = $tables;
					break;
				}
			}
		}

		foreach ( $dupes as $tables ) {
			foreach ( $tables as $rows ) {
				foreach ( $rows as $row ) {
					$ids[] = $row['id'];
				}
			}
		}

		$refs   = self::refs_for( $ids );
		$groups = array();

		foreach ( $dupes as $key => $tables ) {
			$groups[ $key ] = self::group( $tables, $refs, $state['context'] );
		}

		$groups = self::sorted( $groups );

		update_option(
			self::OPT_REPORT,
			array(
				'v'        => self::REPORT_VERSION,
				'read'     => time(),
				'started'  => (int) $state['started'],
				'totals'   => array(
					'students' => (int) $state['stats']['students_seen'],
					'reports'  => (int) $state['stats']['reports_seen'],
					'feedback' => (int) $state['stats']['feedback_seen'],
				),
				'no_email' => (int) $state['stats']['no_email'],
				'groups'   => $groups,
				'counts'   => self::counts( $groups ),
			),
			false
		);

		$state['stats']['addresses'] = count( $groups );
		$state['rows']               = array();
		$state['phase']              = 'done';

		return true;
	}

	/**
	 * One student's classified rows, with the address to show and what the site points at.
	 *
	 * @param array $tables  Table => reduced rows.
	 * @param array $refs    Record ID => site references, for any rows (only this student's are kept).
	 * @param array $context As for `WPCPM_Duplicate_Rules::classify()`.
	 * @return array
	 */
	private static function group( array $tables, array $refs, array $context ) {
		$own = array();

		foreach ( $tables as $rows ) {
			foreach ( $rows as $row ) {
				if ( ! empty( $refs[ $row['id'] ] ) ) {
					$own[ $row['id'] ] = $refs[ $row['id'] ];
				}
			}
		}

		$group          = WPCPM_Duplicate_Rules::classify( $tables, $own, $context );
		$group['refs']  = $own;
		$group['email'] = '';
		$group['last']  = '';

		foreach ( WPCPM_Duplicate_Rules::TABLES as $table ) {
			foreach ( $group['rows'][ $table ] as $row ) {
				if ( '' === $group['email'] ) {
					$group['email'] = $row['email'];
				}
				$group['last'] = max( $group['last'], $row['created'] );
			}
		}

		return $group;
	}

	/**
	 * Ready students first, then those needing a decision; the most recent activity first in each.
	 *
	 * @param array $groups Key => group.
	 * @return array
	 */
	private static function sorted( array $groups ) {
		uasort(
			$groups,
			static function ( $a, $b ) {
				if ( $a['verdict'] !== $b['verdict'] ) {
					return WPCPM_Duplicate_Rules::READY === $a['verdict'] ? -1 : 1;
				}

				return strcmp( (string) $b['last'], (string) $a['last'] );
			}
		);

		return $groups;
	}

	/**
	 * The numbers the screen's tiles and the dashboard read.
	 *
	 * @param array $groups Key => group.
	 * @return array `addresses`, `ready`, `decide`, and table => n under `candidates` and `held`.
	 */
	private static function counts( array $groups ) {
		$counts = array(
			'addresses'  => count( $groups ),
			'ready'      => 0,
			'decide'     => 0,
			'candidates' => array_fill_keys( WPCPM_Duplicate_Rules::TABLES, 0 ),
			'held'       => array_fill_keys( WPCPM_Duplicate_Rules::TABLES, 0 ),
		);

		foreach ( $groups as $group ) {
			++$counts[ WPCPM_Duplicate_Rules::READY === $group['verdict'] ? 'ready' : 'decide' ];

			foreach ( $group['rows'] as $table => $rows ) {
				foreach ( $rows as $row ) {
					if ( WPCPM_Duplicate_Rules::DELETE === $row['proposal'] ) {
						++$counts['candidates'][ $table ];
					} elseif ( WPCPM_Duplicate_Rules::REVIEW === $row['proposal'] ) {
						++$counts['held'][ $table ];
					}
				}
			}
		}

		return $counts;
	}

	/**
	 * End the run on an error, keeping the last good report (decision 3.4).
	 *
	 * @param WP_Error $error What went wrong.
	 */
	private static function fail( WP_Error $error ) {
		update_option( self::OPT_ERROR, $error->get_error_message(), false );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Record the finish and clear the working state. The report itself was written by `finish`'s
	 * phase, which is why nothing of the state is needed here.
	 */
	private static function finish() {
		update_option( self::OPT_LAST, time(), false );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Claim the right to run a tick: `add_option()`'s test-and-set, with a stale takeover.
	 *
	 * @return bool
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
	 * Release the tick lock.
	 */
	private static function release_lock() {
		delete_option( self::OPT_LOCK );
	}

	/**
	 * Estimated completion percentage, from the phases' weights and their steps so far.
	 *
	 * @param array $state Scan state.
	 * @return int
	 */
	private static function percent( array $state ) {
		$phases = self::phases();
		$phase  = isset( $state['phase'] ) ? (string) $state['phase'] : '';

		if ( '' === $phase || 'done' === $phase ) {
			return isset( $state['phase'] ) ? 100 : 0;
		}

		$done = 0;

		foreach ( $phases as $key => $spec ) {
			if ( $key === $phase ) {
				$steps    = isset( $state['steps'][ $key ] ) ? (int) $state['steps'][ $key ] : 0;
				$expected = max( 1, (int) $spec['steps'] );
				$done    += (int) round( $spec['weight'] * min( 1, $steps / $expected ) );
				break;
			}

			$done += (int) $spec['weight'];
		}

		return max( 0, min( 99, $done ) );
	}

	/**
	 * One line about the phase, from the counts.
	 *
	 * @param string $phase The phase.
	 * @param array  $stats The counts.
	 * @return string
	 */
	private static function phase_detail( $phase, array $stats ) {
		switch ( $phase ) {
			case 'students':
				/* translators: %d: Students rows read so far. */
				return sprintf( __( '%d Students rows read', 'wpcredits-program-manager' ), (int) $stats['students_seen'] );
			case 'reports':
				/* translators: %d: Students Reports rows read so far. */
				return sprintf( __( '%d Students Reports rows read', 'wpcredits-program-manager' ), (int) $stats['reports_seen'] );
			case 'feedback':
				/* translators: %d: Feedback rows read so far. */
				return sprintf( __( '%d Feedback rows read', 'wpcredits-program-manager' ), (int) $stats['feedback_seen'] );
			case 'finish':
				return __( 'Grouping the rows by address', 'wpcredits-program-manager' );
		}

		return '';
	}
}
```

Load it:

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -135,6 +135,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-profi
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
```

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -141,6 +141,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-ch
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-rules.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicates-scan.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
 WPCPM_Modules::uninstall();
```

- [ ] **Step 4: Run them and see them pass.** Expected last lines: `ALL PASS (35 checks)` and `ALL PASS (77 checks)`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-duplicates-scan.php bin/test-sponsors-sync.php includes/tools/class-wpcpm-duplicates-scan.php uninstall.php wpcredits-program-manager.php
git commit -m "Student Duplicate Finder: the scan, three tables every three hours at the fifth offset, duplicates kept and a failed read ending the run"
```

---

### Task 5: The vault

**No copy, no delete** (decision 3.7). One private post per deleted row, its cells sealed with `WPCPM_Secret`, written `pending` before the delete is asked for and marked `deleted` once Airtable confirms. A daily job erases the cells after 30 days and settles what stayed pending by asking Airtable whether the row is still there; the post stays as the log entry, with the table, the record ID, the created date, the status, who and when, and never a name or an address (spec section 8).

**Files:**
- Create: `includes/tools/class-wpcpm-duplicate-vault.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (a `require_once` each, after the scan's)
- Test: `bin/test-duplicate-vault.php` (new)

**Interfaces:**
- Consumes: `WPCPM_Secret::can_encrypt()`, `seal_for_option()` and `unseal_from_option()`; Task 1's `reduce()` and `TABLES`; Task 4's `TABLE_SETTINGS`; `WPCPM_Airtable::get_record()` and `is_record_id()`.
- Produces: `final class WPCPM_Duplicate_Vault`, with `POST_TYPE` (`wpcpm_dup_copy`), `CRON_PURGE` (`wpcpm_duplicates_purge`), `KEEP_DAYS` (30), `SETTLE_AFTER` (3600), the meta keys `META_TABLE`, `META_RECORD`, `META_CREATED`, `META_STATUS`, `META_READ`, `META_STATE` and `META_EXPIRES`, and the states `STATE_PENDING`, `STATE_DELETED` and `STATE_ERASED`; and `register()`, `keep( $table, array $record, $actor, $read_at = 0 )` (the copy's post ID, or a `WP_Error`, with nothing stored), `confirm( array $copy_ids )`, `discard( $copy_id )`, `entries( $limit = 50 )` (each `copy`, `table`, `record`, `created`, `status`, `state`, `when`, `by` and `expires`), `view( $copy_id )` (the record, or a `WP_Error` once erased), `purge( $exists = null, $now = null )` (the counts `erased`, `settled_deleted`, `settled_kept` and `unsettled`), `schedule()`, `deactivate()`, `delete_all()` and `still_in_airtable( $table, $record )` (true, false for a 404, null when Airtable would not say).

- [ ] **Step 1: Write the failing checks.** Create `bin/test-duplicate-vault.php`:

```php
<?php
/**
 * The Student Duplicate Finder's copies of deleted rows, and the log they become.
 *
 * What each block pins, and why:
 *
 * - **No copy in the clear.** A copy holds a student's name, address, grades and answers, so its
 *   cells are sealed with the site key (the real `WPCPM_Secret`, on a real OpenSSL) and nothing
 *   readable is left on the post: not the content, not the title, not a meta row.
 * - A copy that went wrong is never half-written: a bad table or record ID stores nothing.
 * - The copy opens again for View copy, whole, and stops opening once it is erased.
 * - **The daily job, on a clock the suite holds:** a copy is erased after thirty days and its post
 *   stays as the log entry; a copy still pending is left alone for an hour, then settled by asking
 *   whether its row is still in Airtable (still there: the copy goes; gone: it is logged as
 *   deleted; no answer: it waits for the next day).
 * - Uninstall takes every copy with it.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-vault.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']  = array();
$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['cron']  = array();
$GLOBALS['types'] = array();
// The real clock, not a fixed one: keep() stamps a copy's expiry from time(), so a suite clock
// that differs from it by a day would move every thirty-day check by that day.
$GLOBALS['now']   = time();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_title = '', $post_content = '', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function register_post_type( $type, $args ) { $GLOBALS['types'][ $type ] = $args; return (object) $args; }
function wp_insert_post( $args, $wp_error = false ) {
	static $next = 100;
	$post                = new WP_Post();
	$post->ID            = ++$next;
	$post->post_type     = $args['post_type'];
	$post->post_status   = $args['post_status'];
	$post->post_title    = $args['post_title'];
	$post->post_content  = $args['post_content'];
	$post->post_author   = (int) $args['post_author'];
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function wp_update_post( $args ) {
	if ( isset( $GLOBALS['posts'][ (int) $args['ID'] ] ) && array_key_exists( 'post_content', $args ) ) {
		$GLOBALS['posts'][ (int) $args['ID'] ]->post_content = $args['post_content'];
	}
	return (int) $args['ID'];
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['meta'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$posts = array_filter( $GLOBALS['posts'], static function ( $p ) use ( $args ) { return $p->post_type === $args['post_type']; } );
	krsort( $posts );
	if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 ) {
		$posts = array_slice( $posts, 0, (int) $args['numberposts'], true );
	}
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_keys( $posts );
	}
	return array_values( $posts );
}
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_schedule_event( $ts, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; $GLOBALS['cron_recurrence'][ $hook ] = $recurrence; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * A Students Reports record in the shape the API returns.
 *
 * @param string $tag The record ID's tag.
 * @return array
 */
function report_record( $tag ) {
	return array(
		'id'          => 'rec' . str_pad( strtoupper( $tag ), 14, '0' ),
		'createdTime' => '2026-02-02T10:00:00.000Z',
		'fields'      => array(
			'Email'                                 => 'student@example.test',
			'Name'                                  => 'A Student',
			'Status'                                => 'Not moving forward',
			'Beginner WordPress User - final grade' => 88,
		),
	);
}

/**
 * Everything the posts and their meta hold for one copy, as one string.
 *
 * @param int $id Post ID.
 * @return string
 */
function stored( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_title . ' ' . $post->post_content . ' ' . json_encode( $GLOBALS['meta'][ $id ] ) : '';
}

echo "\n=== The post type ===\n";

WPCPM_Duplicate_Vault::register();
$args = $GLOBALS['types'][ WPCPM_Duplicate_Vault::POST_TYPE ];
ck( 'a name WordPress will register: twenty characters at most', strlen( WPCPM_Duplicate_Vault::POST_TYPE ) <= 20, true );
ck( 'private everywhere: not public, no screen, not in REST, not searchable', array( $args['public'], $args['publicly_queryable'], $args['show_ui'], $args['show_in_rest'], $args['exclude_from_search'] ), array( false, false, false, false, true ) );
ck( 'the scan leaves the finder\'s own copies out of the site references', in_array( WPCPM_Duplicate_Vault::POST_TYPE, array( 'wpcpm_dup_copy' ), true ), true );

echo "\n=== A copy, sealed ===\n";

$record = report_record( 'kept' );
$copy   = WPCPM_Duplicate_Vault::keep( 'reports', $record, 7, 1788990000 );

ck( 'keep() returns the copy\'s post ID', is_int( $copy ) && $copy > 0, true );
ck( 'nothing readable is stored: no address, no name, no grade anywhere on the post', array( false !== strpos( stored( $copy ), 'student@example.test' ), false !== strpos( stored( $copy ), 'A Student' ), false !== strpos( stored( $copy ), '"88"' ) || false !== strpos( stored( $copy ), ':88' ) ), array( false, false, false ) );
ck( 'the content is the sealed blob, base64', 1 === preg_match( '/^[A-Za-z0-9+\/]+=*$/', get_post( $copy )->post_content ), true );
ck( 'the reference is kept beside it: table, record, created date, status, the scan\'s read time', array( get_post_meta( $copy, WPCPM_Duplicate_Vault::META_TABLE ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_RECORD ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_CREATED ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATUS ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_READ ) ), array( 'reports', $record['id'], '2026-02-02', 'Not moving forward', 1788990000 ) );
ck( 'pending until Airtable confirms the delete', get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATE ), 'pending' );
ck( 'private, and authored by the manager who deleted it', array( get_post( $copy )->post_status, get_post( $copy )->post_author ), array( 'private', 7 ) );
ck( 'kept for thirty days', get_post_meta( $copy, WPCPM_Duplicate_Vault::META_EXPIRES ) - time() >= 30 * DAY_IN_SECONDS - 5, true );

$before = count( $GLOBALS['posts'] );
ck( 'a table the finder does not know is refused', WPCPM_Duplicate_Vault::keep( 'mentors', $record, 7 )->get_error_code(), 'wpcpm_duplicates_bad_copy' );
ck( 'and so is a record ID that is not one', WPCPM_Duplicate_Vault::keep( 'reports', array( 'id' => 'rec123' ), 7 )->get_error_code(), 'wpcpm_duplicates_bad_copy' );
ck( 'with nothing written for either', count( $GLOBALS['posts'] ), $before );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php' );
ck( 'a site that cannot encrypt keeps no copy, and so deletes nothing: keep() asks first', false !== strpos( $source, 'if ( ! WPCPM_Secret::can_encrypt() ) {' ), true );

echo "\n=== View copy ===\n";

ck( 'the copy opens whole', WPCPM_Duplicate_Vault::view( $copy ), $record );
ck( 'a post that is not a copy does not', WPCPM_Duplicate_Vault::view( 99999 )->get_error_code(), 'wpcpm_duplicates_no_copy' );

echo "\n=== Confirmed, discarded, and the log ===\n";

WPCPM_Duplicate_Vault::confirm( array( $copy ) );
ck( 'a confirmed copy is the log entry for a deleted row', get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATE ), 'deleted' );

$refused = WPCPM_Duplicate_Vault::keep( 'reports', report_record( 'refused' ), 7 );
WPCPM_Duplicate_Vault::discard( $refused );
ck( 'a copy of a row that was not deleted after all is removed', get_post( $refused ), null );

$GLOBALS['now'] += 60;
$second  = WPCPM_Duplicate_Vault::keep( 'feedback', array( 'id' => 'rec' . str_pad( 'SECOND', 14, '0' ), 'createdTime' => '2026-02-03T10:00:00.000Z', 'fields' => array( 'Email' => 'student@example.test', 'Course' => 'In Sensei' ) ), 8 );
$entries = WPCPM_Duplicate_Vault::entries( 10 );
ck( 'the log is newest first', array_column( $entries, 'copy' ), array( $second, $copy ) );
ck( 'and an entry says what went, when and by whom, and nothing about the student', array_keys( $entries[1] ), array( 'copy', 'table', 'record', 'created', 'status', 'state', 'when', 'by', 'expires' ) );
ck( 'the older entry, in full', array( $entries[1]['table'], $entries[1]['record'], $entries[1]['state'], $entries[1]['by'] ), array( 'reports', $record['id'], 'deleted', 7 ) );

echo "\n=== The daily job ===\n";

$asked = array();
$ask   = static function ( $answer ) use ( &$asked ) {
	return static function ( $table, $id ) use ( $answer, &$asked ) {
		$asked[] = $id;
		return $answer;
	};
};

$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 60 );
ck( 'a pending copy under an hour old is left alone: its delete may still be running', array( $counts['settled_kept'], $counts['settled_deleted'], $asked ), array( 0, 0, array() ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( null ), $GLOBALS['now'] + 2 * HOUR_IN_SECONDS );
ck( 'an hour on, a copy Airtable will not answer about waits for the next day', array( $counts['unsettled'], get_post_meta( $second, WPCPM_Duplicate_Vault::META_STATE ) ), array( 1, 'pending' ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( false ), $GLOBALS['now'] + 2 * HOUR_IN_SECONDS );
ck( 'a pending copy whose row is gone is logged as deleted', array( $counts['settled_deleted'], get_post_meta( $second, WPCPM_Duplicate_Vault::META_STATE ) ), array( 1, 'deleted' ) );

$third = WPCPM_Duplicate_Vault::keep( 'students', array( 'id' => 'rec' . str_pad( 'THIRD', 14, '0' ), 'createdTime' => '2026-01-27T10:00:00.000Z', 'fields' => array( 'Email' => 'student@example.test' ) ), 7 );
$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 2 * HOUR_IN_SECONDS );
ck( 'a pending copy whose row is still in Airtable goes: nothing was deleted', array( $counts['settled_kept'], get_post( $third ) ), array( 1, null ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 29 * DAY_IN_SECONDS );
ck( 'on day twenty-nine every copy still opens', array( $counts['erased'], is_array( WPCPM_Duplicate_Vault::view( $copy ) ) ), array( 0, true ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 31 * DAY_IN_SECONDS );
ck( 'after thirty days the sealed cells are erased', array( $counts['erased'], get_post( $copy )->post_content, get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATE ) ), array( 2, '', 'erased' ) );
ck( 'and the post stays as the log entry, reference intact', array( get_post( $copy ) instanceof WP_Post, get_post_meta( $copy, WPCPM_Duplicate_Vault::META_RECORD ) ), array( true, $record['id'] ) );
ck( 'an erased copy no longer opens', WPCPM_Duplicate_Vault::view( $copy )->get_error_code(), 'wpcpm_duplicates_erased' );

echo "\n=== Schedule and uninstall ===\n";

WPCPM_Duplicate_Vault::schedule();
WPCPM_Duplicate_Vault::schedule();
ck( 'the daily job is scheduled once, daily', array( isset( $GLOBALS['cron'][ WPCPM_Duplicate_Vault::CRON_PURGE ] ), $GLOBALS['cron_recurrence'][ WPCPM_Duplicate_Vault::CRON_PURGE ] ), array( true, 'daily' ) );

WPCPM_Duplicate_Vault::delete_all();
ck( 'uninstall takes every copy and the job with it', array( count( get_posts( array( 'post_type' => WPCPM_Duplicate_Vault::POST_TYPE, 'fields' => 'ids' ) ) ), wp_next_scheduled( WPCPM_Duplicate_Vault::CRON_PURGE ) ), array( 0, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-duplicate-vault.php`. Expected:

```text
PHP Warning:  require_once(includes/tools/class-wpcpm-duplicate-vault.php): Failed to open stream: No such file or directory in bin/test-duplicate-vault.php on line 107
PHP Fatal error:  Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-duplicate-vault.php' (include_path='...') in bin/test-duplicate-vault.php:107
```

- [ ] **Step 3: Write the class.** Create `includes/tools/class-wpcpm-duplicate-vault.php`:

```php
<?php
/**
 * Student Duplicate Finder: the copies of deleted rows, and the log.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per row the finder deletes: a sealed copy of the row for thirty days, then the
 * log entry that says it went.
 *
 * **No copy, no delete** (spec decision 3.7). The copy is written before the delete is asked for,
 * in the state `pending`, and becomes `deleted` only for a row Airtable confirms; a copy still
 * pending the next day is settled by reading its record, so a lost response never leaves a deleted
 * row without its copy, nor a kept row with a log entry saying it went.
 *
 * The cells are sealed with `WPCPM_Secret` (AES-256-GCM), because they are a student's name,
 * address, grades and answers. After thirty days the daily job erases them and keeps the post:
 * the table, the record ID, the created date, the status, who deleted it and when, and never a
 * name or an address, which is the permanent log the owner asked for (spec section 8).
 *
 * The post type follows the plugin's other private records (`wpcpm_mentor_note`): not public, no
 * screen, not in REST, and a capability type nothing is granted, so only this class's own reads
 * and writes reach it.
 */
final class WPCPM_Duplicate_Vault {

	/** Fourteen characters: WordPress silently refuses a post type name longer than twenty. */
	const POST_TYPE = 'wpcpm_dup_copy';

	/** The daily job that erases old copies and settles unconfirmed ones. */
	const CRON_PURGE = 'wpcpm_duplicates_purge';

	/** How long a copy's cells are kept (spec 13: a constant until the owner asks for a setting). */
	const KEEP_DAYS = 30;

	/**
	 * How old a pending copy must be before the daily job settles it.
	 *
	 * A delete in progress holds its copies pending for the seconds its batches take, and a job
	 * that settled one mid-run would read a row about to go as a row that stayed.
	 */
	const SETTLE_AFTER = 3600;

	const META_TABLE   = '_wpcpm_dup_table';
	const META_RECORD  = '_wpcpm_dup_record';
	const META_CREATED = '_wpcpm_dup_created';
	const META_STATUS  = '_wpcpm_dup_status';
	const META_READ    = '_wpcpm_dup_read';
	const META_STATE   = '_wpcpm_dup_state';
	const META_EXPIRES = '_wpcpm_dup_expires';

	const STATE_PENDING = 'pending';
	const STATE_DELETED = 'deleted';
	const STATE_ERASED  = 'erased';

	/**
	 * Register the post type. Hooked on `init` by the tool.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Deleted duplicate rows', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Deleted duplicate row', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'capability_type'     => self::POST_TYPE,
				'map_meta_cap'        => false,
				'supports'            => array( 'author' ),
			)
		);
	}

	/**
	 * Keep a sealed copy of one row, before it is deleted.
	 *
	 * @param string $table   `students`, `reports` or `feedback`.
	 * @param array  $record  The live record, as Airtable returned it just now.
	 * @param int    $actor   The manager deleting it.
	 * @param int    $read_at When the scan the selection came from read the base.
	 * @return int|WP_Error The copy's post ID.
	 */
	public static function keep( $table, array $record, $actor, $read_at = 0 ) {
		if ( ! WPCPM_Secret::can_encrypt() ) {
			return new WP_Error( 'wpcpm_duplicates_no_seal', __( 'This site cannot encrypt, so it cannot keep the copy a delete needs. Nothing was deleted.', 'wpcredits-program-manager' ) );
		}

		$id = isset( $record['id'] ) ? trim( (string) $record['id'] ) : '';

		if ( ! in_array( $table, WPCPM_Duplicate_Rules::TABLES, true ) || ! WPCPM_Airtable::is_record_id( $id ) ) {
			return new WP_Error( 'wpcpm_duplicates_bad_copy', __( 'That row cannot be copied: its table or its record ID is not one this site knows.', 'wpcredits-program-manager' ) );
		}

		$created = isset( $record['createdTime'] ) ? (string) $record['createdTime'] : '';
		$sealed  = WPCPM_Secret::seal_for_option(
			(string) wp_json_encode(
				array(
					'id'          => $id,
					'createdTime' => $created,
					'fields'      => isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array(),
				)
			)
		);

		if ( is_wp_error( $sealed ) ) {
			return $sealed;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => $table . ' ' . $id,
				'post_content' => $sealed,
				'post_author'  => (int) $actor,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$reduced = WPCPM_Duplicate_Rules::reduce( $table, $record, array() );

		update_post_meta( $post_id, self::META_TABLE, $table );
		update_post_meta( $post_id, self::META_RECORD, $id );
		update_post_meta( $post_id, self::META_CREATED, substr( $created, 0, 10 ) );
		update_post_meta( $post_id, self::META_STATUS, $reduced['status'] );
		update_post_meta( $post_id, self::META_READ, (int) $read_at );
		update_post_meta( $post_id, self::META_STATE, self::STATE_PENDING );
		update_post_meta( $post_id, self::META_EXPIRES, time() + self::KEEP_DAYS * DAY_IN_SECONDS );

		return (int) $post_id;
	}

	/**
	 * Mark copies whose rows Airtable confirmed deleted. From here each is a log entry.
	 *
	 * @param int[] $copy_ids Post IDs.
	 */
	public static function confirm( array $copy_ids ) {
		foreach ( array_map( 'intval', $copy_ids ) as $copy_id ) {
			if ( self::is_copy( $copy_id ) ) {
				update_post_meta( $copy_id, self::META_STATE, self::STATE_DELETED );
			}
		}
	}

	/**
	 * Remove a copy whose row was not deleted after all: refused, or still in Airtable.
	 *
	 * @param int $copy_id Post ID.
	 */
	public static function discard( $copy_id ) {
		if ( self::is_copy( $copy_id ) ) {
			wp_delete_post( (int) $copy_id, true );
		}
	}

	/**
	 * The log, newest first: every copy, with what it may still show.
	 *
	 * @param int $limit How many.
	 * @return array[] Each `copy`, `table`, `record`, `created`, `status`, `state`, `when` (unix),
	 *                 `by` (user ID) and `expires` (unix).
	 */
	public static function entries( $limit = 50 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => max( 1, (int) $limit ),
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		$entries = array();

		foreach ( (array) $posts as $post ) {
			$entries[] = array(
				'copy'    => (int) $post->ID,
				'table'   => (string) get_post_meta( $post->ID, self::META_TABLE, true ),
				'record'  => (string) get_post_meta( $post->ID, self::META_RECORD, true ),
				'created' => (string) get_post_meta( $post->ID, self::META_CREATED, true ),
				'status'  => (string) get_post_meta( $post->ID, self::META_STATUS, true ),
				'state'   => (string) get_post_meta( $post->ID, self::META_STATE, true ),
				'when'    => (int) strtotime( (string) $post->post_date_gmt . ' UTC' ),
				'by'      => (int) $post->post_author,
				'expires' => (int) get_post_meta( $post->ID, self::META_EXPIRES, true ),
			);
		}

		return $entries;
	}

	/**
	 * A copy's cells, unsealed, for View copy.
	 *
	 * @param int $copy_id Post ID.
	 * @return array|WP_Error The record: `id`, `createdTime` and `fields`.
	 */
	public static function view( $copy_id ) {
		if ( ! self::is_copy( $copy_id ) ) {
			return new WP_Error( 'wpcpm_duplicates_no_copy', __( 'There is no such copy.', 'wpcredits-program-manager' ) );
		}

		$post = get_post( (int) $copy_id );

		if ( self::STATE_ERASED === get_post_meta( (int) $copy_id, self::META_STATE, true ) || '' === (string) $post->post_content ) {
			return new WP_Error( 'wpcpm_duplicates_erased', __( 'This copy has been erased: copies are kept for 30 days.', 'wpcredits-program-manager' ) );
		}

		$plain = WPCPM_Secret::unseal_from_option( (string) $post->post_content );

		if ( is_wp_error( $plain ) ) {
			return $plain;
		}

		$record = json_decode( (string) $plain, true );

		return is_array( $record ) ? $record : new WP_Error( 'wpcpm_duplicates_unreadable', __( 'This copy could not be read.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The daily job: erase what is past its thirty days, and settle what is still pending.
	 *
	 * @param callable|null $exists `function( $table, $record ) : bool|null` - whether the row is
	 *                              still in Airtable, or null when that could not be learned. The
	 *                              default asks Airtable; the suites hand in their own.
	 * @param int|null      $now    Unix time, for the suites.
	 * @return array Counts: `erased`, `settled_deleted`, `settled_kept`, `unsettled`.
	 */
	public static function purge( $exists = null, $now = null ) {
		$now    = null === $now ? time() : (int) $now;
		$exists = is_callable( $exists ) ? $exists : array( __CLASS__, 'still_in_airtable' );
		$counts = array(
			'erased'          => 0,
			'settled_deleted' => 0,
			'settled_kept'    => 0,
			'unsettled'       => 0,
		);

		foreach ( self::all_ids() as $copy_id ) {
			$state = (string) get_post_meta( $copy_id, self::META_STATE, true );

			if ( self::STATE_PENDING === $state ) {
				$post = get_post( $copy_id );

				if ( ! $post || ( $now - (int) strtotime( (string) $post->post_date_gmt . ' UTC' ) ) < self::SETTLE_AFTER ) {
					continue;
				}

				$there = call_user_func( $exists, (string) get_post_meta( $copy_id, self::META_TABLE, true ), (string) get_post_meta( $copy_id, self::META_RECORD, true ) );

				if ( true === $there ) {
					self::discard( $copy_id );
					++$counts['settled_kept'];
					continue;
				}

				if ( false === $there ) {
					update_post_meta( $copy_id, self::META_STATE, self::STATE_DELETED );
					$state = self::STATE_DELETED;
					++$counts['settled_deleted'];
				} else {
					++$counts['unsettled'];
					continue;
				}
			}

			if ( self::STATE_DELETED === $state && (int) get_post_meta( $copy_id, self::META_EXPIRES, true ) <= $now ) {
				wp_update_post(
					array(
						'ID'           => $copy_id,
						'post_content' => '',
					)
				);
				update_post_meta( $copy_id, self::META_STATE, self::STATE_ERASED );
				++$counts['erased'];
			}
		}

		return $counts;
	}

	/**
	 * Put the daily job on the clock whenever it is missing, as the other retention runs do.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_PURGE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PURGE );
		}
	}

	/**
	 * Deactivation: the job off the clock. The copies stay, sealed.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_PURGE );
	}

	/**
	 * Uninstall: every copy, sealed cells and log together, and the job.
	 */
	public static function delete_all() {
		foreach ( self::all_ids() as $copy_id ) {
			wp_delete_post( $copy_id, true );
		}

		wp_clear_scheduled_hook( self::CRON_PURGE );
	}

	/**
	 * Whether a row is still in Airtable: true, false for a 404, null when Airtable would not say.
	 *
	 * @param string $table  `students`, `reports` or `feedback`.
	 * @param string $record Record ID.
	 * @return bool|null
	 */
	public static function still_in_airtable( $table, $record ) {
		$settings = WPCPM_Settings::get();
		$key      = isset( WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] ) ? WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] : '';
		$source   = ( '' !== $key && isset( $settings[ $key ] ) ) ? (string) $settings[ $key ] : '';

		if ( '' === $source ) {
			return null;
		}

		$answer = ( new WPCPM_Airtable( $settings ) )->get_record( $source, (string) $record );

		if ( ! is_wp_error( $answer ) ) {
			return true;
		}

		$data = (array) $answer->get_error_data();

		return ( isset( $data['status'] ) && 404 === (int) $data['status'] ) ? false : null;
	}

	/**
	 * Whether a post ID is one of this class's copies.
	 *
	 * @param int $copy_id Post ID.
	 * @return bool
	 */
	private static function is_copy( $copy_id ) {
		$post = get_post( (int) $copy_id );

		return $post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * Every copy's post ID.
	 *
	 * @return int[]
	 */
	private static function all_ids() {
		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => 'any',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			)
		);
	}
}
```

Load it:

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -136,6 +136,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runne
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
```

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -142,6 +142,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-ch
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-rules.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicates-scan.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-vault.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
 WPCPM_Modules::uninstall();
```

- [ ] **Step 4: Run them and see them pass.** `php bin/test-duplicate-vault.php`. Expected last line: `ALL PASS (31 checks)`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-duplicate-vault.php includes/tools/class-wpcpm-duplicate-vault.php uninstall.php wpcredits-program-manager.php
git commit -m "Student Duplicate Finder: the vault, a sealed copy of each deleted row for thirty days and the log it becomes"
```

---

### Task 6: The switch

Decision 3.10: deleting is a Settings switch, `duplicate_delete_enabled`, off by default and rendered on the Settings screen like `import_enabled`, in a card of its own. While it is off the finder scans and lists, and deletes nothing.

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (the default, and the flag in the list of checkboxes the save reads), `includes/class-wpcpm-admin.php` (the card "Tool: Student Duplicate Finder", after the Mentor Status Checker's)
- Test: `bin/test-settings.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: the setting `duplicate_delete_enabled`, a bool, false until a manager saves it on.

- [ ] **Step 1: Write the failing checks.** Apply:

```diff
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -733,6 +733,14 @@ $GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'api_token' => 'kep
 WPCPM_Settings::add_student_status( 'Marketing Track' );
 ck( 'a saved option with no list starts from the default one, and keeps its other settings', array( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] ), array( array_merge( WPCPM_Settings::defaults()['student_statuses'], array( 'Marketing Track' ) ), 'kept' ) );
 
+echo "\n=== The Student Duplicate Finder's switch (1.102.0) ===\n";
+
+// Off until a manager turns it on, the way the import's switch shipped: a delete removes rows from
+// the shared base, so the finder ships scanning and listing and deleting nothing (spec decision
+// 3.10). The generic checks above already hold that the box is rendered and that it can be flipped.
+ck( 'deleting duplicates is off until a manager turns it on', isset( WPCPM_Settings::defaults()['duplicate_delete_enabled'] ) ? WPCPM_Settings::defaults()['duplicate_delete_enabled'] : null, false );
+ck( 'and the switch is a checkbox of its own on the settings screen', false !== strpos( $admin, 'name="duplicate_delete_enabled"' ), true );
+
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
 
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-settings.php`. Expected:

```text
FAIL deleting duplicates is off until a manager turns it on
FAIL and the switch is a checkbox of its own on the settings screen
```

- [ ] **Step 3: Write the switch.** Apply:

```diff
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -180,6 +180,9 @@ class WPCPM_Settings {
 			// Roster import by institutions. Off until it has run on the pilot, since
 			// every import is a write to the shared base.
 			'import_enabled'                => false,
+			// Deleting from the Student Duplicate Finder. Off until a manager turns it on: every
+			// delete removes rows from the shared base (1.102.0).
+			'duplicate_delete_enabled'      => false,
 			// Days an invitation to join an institution's account is kept once it has
 			// lapsed, so a manager can still see who was invited and never came.
 			'invite_retention_days'         => 30,
@@ -402,7 +405,7 @@ class WPCPM_Settings {
 		// case: it forwards every checkbox it renders as a boolean, ticked or not, so
 		// unticking one still switches it off through this same guarded read. Absent means
 		// "leave alone" only for callers narrower than the form.
-		foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled', 'report_autodraft', 'sponsor_home', 'tools_students', 'tools_mentors', 'sponsor_applications_enabled' ) as $flag ) {
+		foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled', 'report_autodraft', 'sponsor_home', 'tools_students', 'tools_mentors', 'sponsor_applications_enabled', 'duplicate_delete_enabled' ) as $flag ) {
 			if ( array_key_exists( $flag, $input ) ) {
 				$clean[ $flag ] = ! empty( $input[ $flag ] );
 			}
```

```diff
--- a/includes/class-wpcpm-admin.php
+++ b/includes/class-wpcpm-admin.php
@@ -534,6 +534,8 @@ class WPCPM_Admin {
 
 		$this->render_checker_settings( $settings );
 
+		$this->render_duplicate_settings( $settings );
+
 		$this->render_handbook_settings( $settings );
 
 		$this->render_two_factor_settings( $settings );
@@ -1214,6 +1216,34 @@ class WPCPM_Admin {
 		echo '</div>';
 	}
 
+	/**
+	 * The Student Duplicate Finder's one switch: whether deleting is on.
+	 *
+	 * Here and not on the finder's own screen, the way the import's switch is: a delete removes rows
+	 * from the shared base, so it is turned on deliberately, by somebody reading what it does, and
+	 * until then the finder scans and lists and deletes nothing (spec decision 3.10).
+	 *
+	 * @param array $settings Current settings.
+	 */
+	private function render_duplicate_settings( array $settings ) {
+		echo '<div class="wpcpm-card">';
+		printf(
+			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
+			esc_html__( 'Tool: Student Duplicate Finder', 'wpcredits-program-manager' ),
+			esc_html__( 'Tool', 'wpcredits-program-manager' )
+		);
+		echo '<table class="form-table" role="presentation"><tbody>';
+		printf(
+			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="duplicate_delete_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p></td></tr>',
+			esc_html__( 'Deleting duplicates', 'wpcredits-program-manager' ),
+			checked( ! empty( $settings['duplicate_delete_enabled'] ), true, false ),
+			esc_html__( 'Let program managers delete the duplicated rows they select and confirm', 'wpcredits-program-manager' ),
+			esc_html__( 'Off by default. While it is off the finder scans and lists, and deletes nothing. A delete removes rows from Students, Students Reports and Feedback in the shared base, and the finder keeps a sealed copy of each row for 30 days.', 'wpcredits-program-manager' )
+		);
+		echo '</tbody></table>';
+		echo '</div>';
+	}
+
 	/**
 	 * One number input row on the settings form.
 	 *
```

- [ ] **Step 4: Run them and see them pass.** `php bin/test-settings.php`. Expected last line: `ALL PASS`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-settings.php includes/class-wpcpm-admin.php includes/class-wpcpm-settings.php
git commit -m "Student Duplicate Finder: the switch that turns deleting on, off by default"
```

---

### Task 7: The delete

Spec 7.3 from step 2 to step 10, as one call that needs no request. **The stored report is the menu, not the authority** (decision 3.6): the selected addresses are read again from Airtable, lowercased on both sides, in the tables a row was chosen from, so the chosen rows come back with their siblings; the site references are read again from the database; every rule is asked again; and only then is a sealed copy of each row kept, pending, before the deletes go ten at a time, children first (decision 3.8). A copy Airtable confirms becomes the log entry; a copy of a row that was sent and not confirmed stays pending for the daily job; a copy of a row never sent is removed (spec 7.5).

**Files:**
- Create: `includes/tools/class-wpcpm-duplicate-delete.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (a `require_once` each, after the vault's)
- Test: `bin/test-duplicate-delete.php` (new)

**Interfaces:**
- Consumes: Task 1's `expand()`, `recheck()`, `reduce()`, `key()`, `EMAIL`, `TABLES` and `DELETE_ORDER`; Task 2's `delete_records()` and the client's `formula_in( $field, $values, true )` and `fetch_page()`; Task 4's `report()`, `is_running()`, `context()`, `refs_for()`, `drop_deleted()` and `TABLE_SETTINGS`; Task 5's `keep()`, `confirm()`, `discard()` and `KEEP_DAYS`; Task 6's switch; `WPCPM_Secret::can_encrypt()`.
- Produces: `WPCPM_Duplicate_Delete::run( array $keys, array $pairs, $actor )`, the outcome the screen prints: `status` (`switched-off`, `scan-running`, `no-seal`, `nothing`, `read-failed`, `copy-failed`, `deleted` or `stopped`), and where they apply `deleted` (table => n), `refused` (what `expand()` dropped and `recheck()` refused, each with its `code`), `detail` (Airtable's own sentence) and `until` (when the copies are erased).

- [ ] **Step 1: Write the failing checks.** Create `bin/test-duplicate-delete.php`:

```php
<?php
/**
 * The Student Duplicate Finder's delete: a posted selection in, the outcome the screen prints out.
 *
 * `WPCPM_Duplicate_Delete::run()` against a base this suite holds, with the real rules, the real
 * scan's report, the real vault and the real seal. What each block pins, and why:
 *
 * - **Nothing reaches Airtable before the guards say yes**: the switch, a scan in progress, a
 *   selection that stands for nothing. No read, no copy, no delete.
 * - The Slack request's example goes in one confirmation, read again from the base first by its
 *   lowercased address, children first (Feedback, Students Reports, Students), each row sealed
 *   before it goes and logged once Airtable says it went, and the student leaves the stored list.
 * - **The stored report is the menu, not the authority**: a row the site began pointing at since
 *   the scan, or a row that gained answers, is refused, and nothing is kept or written for it.
 * - Never the last row an address has in a table, however the ticks fell.
 * - Failing closed: a re-read Airtable refuses deletes nothing; a batch Airtable refuses stops the
 *   run, leaves the copies of the rows it was sent pending for the daily job, and removes the
 *   copies of rows never sent.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-delete.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['opts']     = array();
$GLOBALS['posts']    = array();
$GLOBALS['meta']     = array();
$GLOBALS['cron']     = array();
$GLOBALS['calls']    = array();
$GLOBALS['usermeta'] = array();
$GLOBALS['postmeta'] = array();
$GLOBALS['switch']   = true;
$GLOBALS['now']      = time();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_title = '', $post_content = '', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function add_action() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }
function wp_insert_post( $args, $wp_error = false ) {
	static $next = 100;
	$post                = new WP_Post();
	$post->ID            = ++$next;
	$post->post_type     = $args['post_type'];
	$post->post_status   = $args['post_status'];
	$post->post_title    = $args['post_title'];
	$post->post_content  = $args['post_content'];
	$post->post_author   = (int) $args['post_author'];
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function wp_update_post( $args ) { return (int) $args['ID']; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['meta'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$posts = array_filter( $GLOBALS['posts'], static function ( $p ) use ( $args ) { return $p->post_type === $args['post_type']; } );
	krsort( $posts );
	return ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? array_keys( $posts ) : array_values( $posts );
}

/* ---- the other pieces, stubbed to their contracts ----------------------- */

/**
 * Airtable, as the suite holds it in $GLOBALS['base']: reads answer from it, deletes take from it.
 *
 * A read with a formula answers only the rows whose lowercased Email the formula names, which is
 * what `formula_in()` with lowercasing asks Airtable for.
 */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function fetch_page( $table, array $args = array() ) {
		$formula            = isset( $args['formula'] ) ? (string) $args['formula'] : '';
		$GLOBALS['calls'][] = array( 'read', $table, $formula );
		if ( '' !== $formula && ! empty( $GLOBALS['read_fails'] ) ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 503): no further detail' );
		}
		$records = isset( $GLOBALS['base'][ $table ] ) ? $GLOBALS['base'][ $table ] : array();
		if ( '' !== $formula ) {
			preg_match_all( "/= '([^']*)'/", $formula, $named );
			$records = array_values(
				array_filter(
					$records,
					static function ( $record ) use ( $named ) {
						return in_array( strtolower( trim( isset( $record['fields']['Email'] ) ? (string) $record['fields']['Email'] : '' ) ), $named[1], true );
					}
				)
			);
		}
		return array( 'records' => $records, 'offset' => null );
	}
	public function formula_in( $field, array $values, $lower = false ) {
		$tests = array();
		foreach ( $values as $value ) {
			$tests[] = sprintf( "LOWER({%s}) = '%s'", $field, strtolower( $value ) );
		}
		return 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ',', $tests ) . ')';
	}
	public function delete_records( $table, array $ids ) {
		$GLOBALS['calls'][] = array( 'delete', $table, $ids );
		if ( isset( $GLOBALS['refuse'][ $table ] ) ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): INVALID_REQUEST_UNKNOWN', array( 'status' => 422, 'deleted' => array() ) );
		}
		$GLOBALS['base'][ $table ] = array_values(
			array_filter(
				$GLOBALS['base'][ $table ],
				static function ( $record ) use ( $ids ) {
					return ! in_array( $record['id'], $ids, true );
				}
			)
		);
		return array_fill_keys( $ids, true );
	}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'base_id' => 'appTEST', 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK', 'duplicate_delete_enabled' => $GLOBALS['switch'] ); }
	public static function is_connected() { return true; }
}
class WPCPM_Mentors_Sync {
	public static function tracked_statuses( $settings = null ) {
		$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
		return array( 'active' => $active, 'past' => array( 'Graduate', 'Dropped out' ), 'all' => $active );
	}
}
class WPCPM_Program {
	public static function labels() { return array( 'In Sensei' => '150h' ); }
	public static function track( $status ) { return '150h'; }
}
class WPCPM_Student_Report_Form {
	public static function fields( $track ) { return array( 'Beginner WordPress User - final grade' => array() ); }
}
class Test_WPDB {
	public $usermeta = 'wp_usermeta', $postmeta = 'wp_postmeta', $posts = 'wp_posts';
	public function prepare( $sql, $args ) { return array( $sql, (array) $args ); }
	public function get_results( $prepared, $output = null ) {
		list( $sql, $ids ) = $prepared;
		$rows = array();
		foreach ( false !== strpos( $sql, 'wp_usermeta' ) ? $GLOBALS['usermeta'] : $GLOBALS['postmeta'] as $row ) {
			if ( in_array( $row['meta_value'], $ids, true ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new Test_WPDB();

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * A record ID that says what it is.
 *
 * @param string $tag Up to fourteen letters and digits.
 * @return string
 */
function rid( $tag ) {
	return 'rec' . str_pad( strtoupper( $tag ), 14, '0' );
}

/**
 * A record in the shape the API returns.
 *
 * @param string $tag     Record ID tag.
 * @param string $created ISO 8601.
 * @param array  $fields  Cells.
 * @return array
 */
function record( $tag, $created, array $fields ) {
	return array(
		'id'          => rid( $tag ),
		'createdTime' => $created,
		'fields'      => $fields,
	);
}

/**
 * The base: the Slack request's example, Ready, and a second student whose newest Students row did
 * not move forward while the older one is live, which is a decision.
 */
function base() {
	$GLOBALS['base'] = array(
		'tblSTUDENTS' => array(
			record( 'stuold', '2026-01-27T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
			record( 'stunew', '2026-09-09T10:00:00.000Z', array( 'Email' => 'Student@example.test', 'Full Name' => 'A Student', 'Status' => 'In Sensei' ) ),
			record( 'othold', '2026-02-01T10:00:00.000Z', array( 'Email' => 'other@example.test', 'Full Name' => 'Other Student', 'Status' => 'In Sensei' ) ),
			record( 'othnew', '2026-08-01T10:00:00.000Z', array( 'Email' => 'other@example.test', 'Full Name' => 'Other Student', 'Status' => 'Not moving forward' ) ),
		),
		'tblREPORTS'  => array(
			record( 'repold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
			record( 'repnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'In Sensei' ) ),
		),
		'tblFEEDBACK' => array(
			record( 'fbold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student' ) ),
			record( 'fbnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Course' => 'In Sensei' ) ),
		),
	);
}

/** A fresh base, a fresh scan of it, and nothing recorded yet. */
function fresh() {
	base();
	$GLOBALS['posts']      = array();
	$GLOBALS['meta']       = array();
	$GLOBALS['usermeta']   = array();
	$GLOBALS['postmeta']   = array();
	$GLOBALS['refuse']     = array();
	$GLOBALS['read_fails'] = false;
	$GLOBALS['switch']     = true;
	WPCPM_Duplicates_Scan::start();
	for ( $i = 0; $i < 20 && WPCPM_Duplicates_Scan::is_running(); $i++ ) {
		WPCPM_Duplicates_Scan::run_tick( WPCPM_Duplicates_Scan::BUDGET_AJAX );
	}
	$GLOBALS['calls'] = array();
}

/**
 * What was asked of Airtable since the scan: each call's kind and table, and for a delete its IDs.
 *
 * @param string $kind 'read' or 'delete'.
 * @return array
 */
function asked( $kind ) {
	$out = array();
	foreach ( $GLOBALS['calls'] as $call ) {
		if ( $kind === $call[0] ) {
			$out[] = 'delete' === $kind ? array( $call[1], $call[2] ) : $call[1];
		}
	}
	return $out;
}

/**
 * The copies kept, by record ID, with their state.
 *
 * @return array<string, string>
 */
function copies() {
	$out = array();
	foreach ( array_keys( $GLOBALS['posts'] ) as $id ) {
		$out[ get_post_meta( $id, WPCPM_Duplicate_Vault::META_RECORD ) ] = get_post_meta( $id, WPCPM_Duplicate_Vault::META_STATE );
	}
	ksort( $out );
	return $out;
}

$ready = WPCPM_Duplicate_Rules::key( 'student@example.test' );
$other = WPCPM_Duplicate_Rules::key( 'other@example.test' );

/* ---- the guards --------------------------------------------------------- */

echo "\n=== Nothing reaches Airtable before the guards say yes ===\n";

fresh();
ck( 'the scan found the two students, one Ready', array( array_keys( WPCPM_Duplicates_Scan::report()['groups'] ), WPCPM_Duplicates_Scan::report()['groups'][ $ready ]['verdict'] ), array( array( $ready, $other ), 'ready' ) );

$GLOBALS['switch'] = false;
ck( 'deleting switched off: refused', WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 ), array( 'status' => 'switched-off' ) );
ck( 'and nothing was read, deleted or copied', array( $GLOBALS['calls'], copies() ), array( array(), array() ) );
$GLOBALS['switch'] = true;

WPCPM_Duplicates_Scan::start();
ck( 'a scan in progress: refused, because it is about to write a new list', WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 ), array( 'status' => 'scan-running' ) );
WPCPM_Duplicates_Scan::cancel();

$outcome = WPCPM_Duplicate_Delete::run( array( 'ffffffffffffffff' ), array( 'students:' . rid( 'stunew' ) ), 7 );
ck( 'a selection that stands for nothing: nothing, with why', array( $outcome['status'], array_column( $outcome['refused'], 'code' ) ), array( 'nothing', array( 'unknown', 'not-selectable' ) ) );
ck( 'and still nothing asked of Airtable', array( $GLOBALS['calls'], copies() ), array( array(), array() ) );

/* ---- the Slack example ---------------------------------------------------- */

echo "\n=== The Slack example goes in one confirmation ===\n";

$outcome = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'deleted: one row from each table, nothing refused', array( $outcome['status'], $outcome['deleted'], $outcome['refused'] ), array( 'deleted', array( 'students' => 1, 'reports' => 1, 'feedback' => 1 ), array() ) );
ck( 'and the copies are kept for thirty days', abs( $outcome['until'] - ( time() + 30 * DAY_IN_SECONDS ) ) < 5, true );
ck( 'the base was read again first, in the three tables a row was chosen from', asked( 'read' ), array( 'tblSTUDENTS', 'tblREPORTS', 'tblFEEDBACK' ) );
ck( 'by the address, lowercased', $GLOBALS['calls'][0][2], "LOWER({Email}) = 'student@example.test'" );
ck( 'then children first: Feedback, Students Reports, Students, the older row of each', asked( 'delete' ), array( array( 'tblFEEDBACK', array( rid( 'fbold' ) ) ), array( 'tblREPORTS', array( rid( 'repold' ) ) ), array( 'tblSTUDENTS', array( rid( 'stuold' ) ) ) ) );
ck( 'each row has its copy, confirmed, and the copies name who deleted them', array( copies(), array_values( array_unique( array_map( static function ( $post ) { return $post->post_author; }, $GLOBALS['posts'] ) ) ) ), array( array( rid( 'fbold' ) => 'deleted', rid( 'repold' ) => 'deleted', rid( 'stuold' ) => 'deleted' ), array( 7 ) ) );
ck( 'the newest rows are still in the base', array_column( array_merge( $GLOBALS['base']['tblSTUDENTS'], $GLOBALS['base']['tblREPORTS'], $GLOBALS['base']['tblFEEDBACK'] ), 'id' ), array( rid( 'stunew' ), rid( 'othold' ), rid( 'othnew' ), rid( 'repnew' ), rid( 'fbnew' ) ) );
ck( 'and the student leaves the stored list at once', array_keys( WPCPM_Duplicates_Scan::report()['groups'] ), array( $other ) );

/* ---- the re-check ----------------------------------------------------------- */

echo "\n=== The stored report is the menu, not the authority ===\n";

fresh();
$GLOBALS['usermeta'] = array( array( 'user_id' => 9, 'meta_key' => 'wpcpm_student_record_id', 'meta_value' => rid( 'repold' ) ) );
$outcome             = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a row the site began pointing at since the scan is refused', array_column( $outcome['refused'], 'code', 'id' ), array( rid( 'repold' ) => 'site' ) );
ck( 'the other two go', array( $outcome['deleted'], asked( 'delete' ) ), array( array( 'students' => 1, 'reports' => 0, 'feedback' => 1 ), array( array( 'tblFEEDBACK', array( rid( 'fbold' ) ) ), array( 'tblSTUDENTS', array( rid( 'stuold' ) ) ) ) ) );
ck( 'and nothing is kept for the refused row', copies(), array( rid( 'fbold' ) => 'deleted', rid( 'stuold' ) => 'deleted' ) );

fresh();
$GLOBALS['base']['tblFEEDBACK'][0]['fields']['F1 - How easy was it to get started?'] = 4;
$outcome = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a row that gained answers since the scan is refused as changed', array_column( $outcome['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'changed' ) );

fresh();
$outcome = WPCPM_Duplicate_Delete::run( array(), array( 'students:' . rid( 'othold' ), 'students:' . rid( 'othnew' ) ), 7 );
ck( 'ticking every row a table has refuses them all as the last row', array( $outcome['status'], array_column( $outcome['refused'], 'code', 'id' ) ), array( 'nothing', array( rid( 'othold' ) => 'last-row', rid( 'othnew' ) => 'last-row' ) ) );
ck( 'with nothing deleted and nothing kept', array( asked( 'delete' ), copies() ), array( array(), array() ) );

/* ---- failing closed ----------------------------------------------------------- */

echo "\n=== Failing closed ===\n";

fresh();
$GLOBALS['read_fails'] = true;
$outcome               = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a re-read Airtable refuses deletes nothing and keeps nothing', array( $outcome['status'], asked( 'delete' ), copies() ), array( 'read-failed', array(), array() ) );

fresh();
$GLOBALS['refuse'] = array( 'tblREPORTS' => true );
$outcome           = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a batch Airtable refuses stops the run and says so', array( $outcome['status'], $outcome['deleted'], $outcome['detail'] ), array( 'stopped', array( 'students' => 0, 'reports' => 0, 'feedback' => 1 ), 'Airtable request failed (HTTP 422): INVALID_REQUEST_UNKNOWN' ) );
ck( 'Students was never sent', asked( 'delete' ), array( array( 'tblFEEDBACK', array( rid( 'fbold' ) ) ), array( 'tblREPORTS', array( rid( 'repold' ) ) ) ) );
ck( 'so its copy is removed, the refused row\'s copy waits for the daily job, and the deleted one is logged', copies(), array( rid( 'fbold' ) => 'deleted', rid( 'repold' ) => 'pending' ) );
ck( 'and the stored list drops only the row that went', WPCPM_Duplicates_Scan::report()['groups'][ $ready ]['counts'], array( 'students' => 2, 'reports' => 2, 'feedback' => 1 ) );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php' );
ck( 'no dash but the plain hyphen in the class', 1 === preg_match( '/\x{2013}|\x{2014}/u', $source ), false );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-duplicate-delete.php`. Expected:

```text
PHP Warning:  require_once(includes/tools/class-wpcpm-duplicate-delete.php): Failed to open stream: No such file or directory in bin/test-duplicate-delete.php on line 189
PHP Fatal error:  Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-duplicate-delete.php' (include_path='...') in bin/test-duplicate-delete.php:189
```

- [ ] **Step 3: Write the class.** Create `includes/tools/class-wpcpm-duplicate-delete.php`:

```php
<?php
/**
 * Student Duplicate Finder: the delete.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One confirmed delete, from a posted selection to the outcome the screen prints.
 *
 * The tool's handler checks the capability and the nonce and then hands the selection here, so
 * the whole of spec 7.3 runs, and is tested, without a request. The order is the spec's:
 *
 * 1. the switch is on, no scan is running, and the site can seal;
 * 2. the selection is expanded against the stored report again;
 * 3. the selected addresses are read from Airtable again, in the tables a row was chosen from, so
 *    the chosen rows and their siblings come back together;
 * 4. the site references are read from the database again, and `WPCPM_Duplicate_Rules::recheck()`
 *    asks every question again: **the stored report is the menu, not the authority** (3.6);
 * 5. a sealed copy of each row that passed is kept, pending, before anything is deleted, and a copy
 *    that cannot be kept stops the delete with nothing deleted (3.7);
 * 6. the rows are deleted ten at a time, Feedback first, then Students Reports, then Students (3.8);
 * 7. each copy whose row Airtable confirms becomes the log entry; a copy of a row that was sent and
 *    not confirmed stays pending for the daily job; a copy of a row never sent is removed (7.5);
 * 8. the stored report drops the deleted rows at once.
 */
final class WPCPM_Duplicate_Delete {

	/**
	 * Run a delete.
	 *
	 * @param string[] $keys  Posted student keys.
	 * @param string[] $pairs Posted `table:record` pairs.
	 * @param int      $actor The manager deleting.
	 * @return array The outcome: `status`, and `deleted` (table => n), `refused`, `detail` and
	 *               `until` where they apply.
	 */
	public static function run( array $keys, array $pairs, $actor ) {
		$settings = WPCPM_Settings::get();

		if ( empty( $settings['duplicate_delete_enabled'] ) ) {
			return array( 'status' => 'switched-off' );
		}

		// A scan is about to write a new list over the one this selection was read from.
		if ( WPCPM_Duplicates_Scan::is_running() ) {
			return array( 'status' => 'scan-running' );
		}

		if ( ! WPCPM_Secret::can_encrypt() ) {
			return array( 'status' => 'no-seal' );
		}

		$report = WPCPM_Duplicates_Scan::report();
		$chosen = WPCPM_Duplicate_Rules::expand( $report, $keys, $pairs );

		if ( empty( $chosen['rows'] ) ) {
			return array(
				'status'  => 'nothing',
				'refused' => $chosen['dropped'],
			);
		}

		$airtable = new WPCPM_Airtable( $settings );
		$context  = WPCPM_Duplicates_Scan::context();
		$live     = self::read_live( $airtable, $settings, $report, $chosen['rows'], $context );

		if ( is_wp_error( $live ) ) {
			return array(
				'status' => 'read-failed',
				'detail' => $live->get_error_message(),
			);
		}

		$ids = array();
		foreach ( $live['rows'] as $tables ) {
			foreach ( $tables as $rows ) {
				foreach ( $rows as $row ) {
					$ids[] = $row['id'];
				}
			}
		}

		$checked = WPCPM_Duplicate_Rules::recheck( $chosen['rows'], $live['rows'], WPCPM_Duplicates_Scan::refs_for( $ids ), $context );
		$refused = array_merge( $chosen['dropped'], $checked['refused'] );

		if ( empty( $checked['go'] ) ) {
			return array(
				'status'  => 'nothing',
				'refused' => $refused,
			);
		}

		$copies = array();

		foreach ( $checked['go'] as $pick ) {
			$copy = WPCPM_Duplicate_Vault::keep( $pick['table'], $live['records'][ $pick['id'] ], (int) $actor, isset( $report['read'] ) ? (int) $report['read'] : 0 );

			if ( is_wp_error( $copy ) ) {
				foreach ( $copies as $made ) {
					WPCPM_Duplicate_Vault::discard( $made );
				}

				return array(
					'status'  => 'copy-failed',
					'detail'  => $copy->get_error_message(),
					'refused' => $refused,
				);
			}

			$copies[ $pick['id'] ] = $copy;
		}

		$deleted   = array();
		$attempted = array();
		$error     = null;

		foreach ( WPCPM_Duplicate_Rules::DELETE_ORDER as $table ) {
			$batch = array();

			foreach ( $checked['go'] as $pick ) {
				if ( $table === $pick['table'] ) {
					$batch[] = $pick['id'];
				}
			}

			if ( empty( $batch ) ) {
				continue;
			}

			$attempted[ $table ] = true;
			$answer              = $airtable->delete_records( (string) $settings[ WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] ], $batch );

			if ( is_wp_error( $answer ) ) {
				$data = (array) $answer->get_error_data();

				foreach ( isset( $data['deleted'] ) ? (array) $data['deleted'] : array() as $id ) {
					$deleted[ (string) $id ] = true;
				}

				$error = $answer;
				break;
			}

			foreach ( array_keys( $answer ) as $id ) {
				$deleted[ (string) $id ] = true;
			}
		}

		$confirmed = array();
		$gone      = array();

		foreach ( $checked['go'] as $pick ) {
			if ( isset( $deleted[ $pick['id'] ] ) ) {
				$confirmed[] = $copies[ $pick['id'] ];
				$gone[]      = $pick;
			} elseif ( empty( $attempted[ $pick['table'] ] ) ) {
				WPCPM_Duplicate_Vault::discard( $copies[ $pick['id'] ] );
			}
		}

		WPCPM_Duplicate_Vault::confirm( $confirmed );
		WPCPM_Duplicates_Scan::drop_deleted( $gone );

		$tally = array_fill_keys( WPCPM_Duplicate_Rules::TABLES, 0 );
		foreach ( $gone as $pick ) {
			++$tally[ $pick['table'] ];
		}

		return array(
			'status'  => null === $error ? 'deleted' : 'stopped',
			'deleted' => $tally,
			'refused' => $refused,
			'detail'  => null === $error ? '' : $error->get_error_message(),
			'until'   => time() + WPCPM_Duplicate_Vault::KEEP_DAYS * DAY_IN_SECONDS,
		);
	}

	/**
	 * Every row the base holds now for the selected addresses, in the tables a row was chosen from.
	 *
	 * One formula for all the addresses, lowercased on both sides, and paged: the chosen rows and
	 * their siblings come back together, which is what the last-row rule needs.
	 *
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @param array          $report   The stored report.
	 * @param array          $picks    `expand()`'s rows.
	 * @param array          $context  The scan's context.
	 * @return array|WP_Error `rows` (key => table => reduced rows) and `records` (ID => record).
	 */
	private static function read_live( WPCPM_Airtable $airtable, array $settings, array $report, array $picks, array $context ) {
		$addresses = array();
		$tables    = array();

		foreach ( $picks as $pick ) {
			if ( isset( $report['groups'][ $pick['key'] ]['email'] ) ) {
				$addresses[ strtolower( trim( (string) $report['groups'][ $pick['key'] ]['email'] ) ) ] = true;
			}
			$tables[ $pick['table'] ] = true;
		}

		$formula = $airtable->formula_in( WPCPM_Duplicate_Rules::EMAIL, array_keys( $addresses ), true );
		$rows    = array();
		$records = array();

		foreach ( WPCPM_Duplicate_Rules::TABLES as $table ) {
			if ( empty( $tables[ $table ] ) ) {
				continue;
			}

			$offset = null;
			$pages  = 0;

			do {
				$page = $airtable->fetch_page(
					(string) $settings[ WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] ],
					array(
						'formula' => $formula,
						'offset'  => $offset,
					)
				);

				if ( is_wp_error( $page ) ) {
					return $page;
				}

				foreach ( $page['records'] as $record ) {
					$row = WPCPM_Duplicate_Rules::reduce( $table, (array) $record, $context );
					$key = WPCPM_Duplicate_Rules::key( $row['email'] );

					if ( '' === $key ) {
						continue;
					}

					$rows[ $key ][ $table ][] = $row;
					$records[ $row['id'] ]    = (array) $record;
				}

				$offset = empty( $page['offset'] ) ? null : $page['offset'];
				++$pages;
			} while ( null !== $offset && $pages < 20 );
		}

		return array(
			'rows'    => $rows,
			'records' => $records,
		);
	}
}
```

Load it:

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -137,6 +137,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
```

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -143,6 +143,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-ch
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-rules.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicates-scan.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-vault.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-delete.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
 WPCPM_Modules::uninstall();
```

- [ ] **Step 4: Run them and see them pass.** `php bin/test-duplicate-delete.php`. Expected last line: `ALL PASS (26 checks)`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add bin/test-duplicate-delete.php includes/tools/class-wpcpm-duplicate-delete.php uninstall.php wpcredits-program-manager.php
git commit -m "Student Duplicate Finder: the delete, read again and checked again, a sealed copy of each row kept before Airtable is asked, children first"
```

---

### Task 8: The screen

The tool (decision 3.1) and its screen (spec section 6): the list with its tiles, the confirmation, View copy, the notice after a press and the log. Scan now, the progress tick and Cancel are copied from `WPCPM_Sync_Module` rather than shared (decision 3.9), in its order: **every door checks `WPCPM_Roles::CAP_MANAGE` before its nonce**. The form works without JavaScript; the script adds the live count, Select all ready and Clear, whose buttons ship hidden so nothing on the page does nothing when scripts are off.

**Files:**
- Create: `includes/tools/class-wpcpm-duplicate-finder-screen.php`, `includes/tools/class-wpcpm-duplicate-finder.php`, `assets/js/duplicate-finder.js`, `assets/css/duplicate-finder.css`
- Modify: `includes/class-wpcpm-tools.php` (the finder in `WPCPM_Tools::all()`), `wpcredits-program-manager.php`, `uninstall.php` (a `require_once` each for the screen and the tool, after the delete's)
- Test: `bin/test-duplicate-finder.php` (new); `bin/test-roles.php` (the tool is registered); `bin/test-handlers.php` (the new handlers reach a redirect or a `wp_die()`)

**Interfaces:**
- Consumes: Tasks 1 to 7; `WPCPM_Tool`; `WPCPM_Flash::set()` and `take()`; `WPCPM_Request::id()`, `posted_key()` and `posted_list()`; `WPCPM_Mentors::format_duration()`; `WPCPM_Institutions_Index::row()` where it is loaded.
- Produces:
  - `class WPCPM_Duplicate_Finder extends WPCPM_Tool`, with the constants `ACTION_SCAN` (`wpcpm_duplicates_scan_now`), `ACTION_CANCEL` (`wpcpm_duplicates_cancel`), `ACTION_TICK` (`wpcpm_duplicates_progress`), `ACTION_REVIEW` (`wpcpm_duplicates_review`), `ACTION_DELETE` (`wpcpm_duplicates_delete`), `ACTION_VIEW` (`wpcpm_duplicates_view`), `FLASH` (`duplicates`), `KEY_PATTERN` and `PAIR_PATTERN`; `id()` (`duplicate-finder`, so the page is `wpcpm-tool-duplicate-finder`), `label()`, `description()`, `status_line()`, `boot()`, `activate()`, `deactivate()`, `uninstall()`, `enqueue_assets( $hook_suffix )`, `handle_scan()`, `handle_cancel()`, `handle_tick()`, `handle_delete()`, `render_admin_page()` and `selection_hash( array $keys, array $pairs )`.
  - `final class WPCPM_Duplicate_Finder_Screen`, with `table_names()`, `reason( array $reason, $course = false )`, `refusal( $code )`, `refs( array $refs )`, `render_list( array $args )`, `render_confirm( array $args )` and `render_copy( $copy_id, $record, $url )`.

- [ ] **Step 1: Write the failing checks.** Create `bin/test-duplicate-finder.php`:

```php
<?php
/**
 * The Student Duplicate Finder's screen and handlers.
 *
 * `WPCPM_Duplicate_Finder` and `WPCPM_Duplicate_Finder_Screen` against a report the real scan
 * wrote from a base this suite holds, with the real rules, vault, seal, request readers and
 * flash. What each block pins, and why:
 *
 * - **The capability first, then the nonce, on every door**: the list, the confirmation, View
 *   copy, Scan now, Cancel, the progress tick and Delete. Somebody without the capability meets
 *   wp_die() or a 403, and no nonce is even looked at (spec 3.9 and 7.3).
 * - **The Delete button's nonce is tied to exactly the rows the confirmation listed** (spec
 *   7.2): the same rows in any order are the same token, one row more is another, and a posted
 *   value that is neither a student key nor a table and record pair never reaches the hash.
 * - The list ticks nothing by itself, gives a Ready student one checkbox and a student who needs
 *   a decision one per row that may go, shows a row the site points at as a disabled checkbox
 *   that points at its reason, and says why nothing can be ticked while deleting is switched off.
 *   While a scan runs, the list stays and Review selection waits.
 * - The confirmation lists what a press of Delete removes and what was left out, and Back to the
 *   list returns with the same ticks.
 * - The notice after a delete says what went, what was refused and why, and when to try again.
 * - The log holds no name and no address, and offers View copy behind a nonce keyed to the copy;
 *   a copy opens whole for a manager, and not at all once it is erased.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-finder.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}
class JsonSignal extends Exception {
	public $payload;
	public function __construct( array $payload ) { parent::__construct( 'json' ); $this->payload = $payload; }
}

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['meta']      = array();
$GLOBALS['cron']      = array();
$GLOBALS['hooks']     = array();
$GLOBALS['enqueued']  = array();
$GLOBALS['usermeta']  = array();
$GLOBALS['postmeta']  = array();
$GLOBALS['nonces']    = array();
$GLOBALS['caps']      = true;
$GLOBALS['uid']       = 1;
$GLOBALS['switch']    = false;
$GLOBALS['connected'] = true;
$GLOBALS['now']       = time();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_title = '', $post_content = '', $post_author = 0, $post_date_gmt = '';
}
class WP_User {
	public $ID = 0, $display_name = '';
	public function __construct( $id, $name ) { $this->ID = $id; $this->display_name = $name; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return $s; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function human_time_diff( $a, $b = null ) { return '2 hours'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value ); }
function wp_create_nonce( $action ) { return 'n-' . $action; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=' . wp_create_nonce( $action ); }
function wp_nonce_field( $action ) { printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( $action ) ) ); }
function check_admin_referer( $action = -1, $arg = '_wpnonce' ) { $GLOBALS['nonces'][] = $action; return 1; }
function check_ajax_referer( $action = -1, $arg = false ) { $GLOBALS['nonces'][] = $action; return 1; }
function wp_die( $m = '', $c = 0 ) { throw new DieSignal( (string) $m ); }
function wp_safe_redirect( $u ) { throw new RedirectSignal( $u ); }
function wp_send_json_error( $d = null, $c = null ) { throw new JsonSignal( array( 'success' => false, 'data' => $d, 'code' => $c ) ); }
function wp_send_json_success( $d = null ) { throw new JsonSignal( array( 'success' => true, 'data' => $d ) ); }
function submit_button( $text, $type = '', $name = '', $wrap = true ) { printf( '<button type="submit" class="button">%s</button>', esc_html( $text ) ); }
function get_userdata( $id ) { return 7 === (int) $id ? new WP_User( 7, 'Pat Manager' ) : false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; return true; }
function wp_enqueue_style( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'style', $handle, $deps ); }
function wp_enqueue_script( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'script', $handle, $deps ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_get_scheduled_event( $hook ) { return false; }
function wp_schedule_event( $ts, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }
function wp_insert_post( $args, $wp_error = false ) {
	static $next = 100;
	$post                = new WP_Post();
	$post->ID            = ++$next;
	$post->post_type     = $args['post_type'];
	$post->post_status   = $args['post_status'];
	$post->post_title    = $args['post_title'];
	$post->post_content  = $args['post_content'];
	$post->post_author   = (int) $args['post_author'];
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function wp_update_post( $args ) {
	if ( isset( $GLOBALS['posts'][ (int) $args['ID'] ] ) && array_key_exists( 'post_content', $args ) ) {
		$GLOBALS['posts'][ (int) $args['ID'] ]->post_content = $args['post_content'];
	}
	return (int) $args['ID'];
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['meta'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$posts = array_filter( $GLOBALS['posts'], static function ( $p ) use ( $args ) { return $p->post_type === $args['post_type']; } );
	krsort( $posts );
	if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 ) {
		$posts = array_slice( $posts, 0, (int) $args['numberposts'], true );
	}
	return ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? array_keys( $posts ) : array_values( $posts );
}
require_once __DIR__ . '/stubs/caps.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}
/** Airtable, as the suite holds it in $GLOBALS['base']. The scan reads whole tables. */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function fetch_page( $table, array $args = array() ) {
		return array( 'records' => isset( $GLOBALS['base'][ $table ] ) ? $GLOBALS['base'][ $table ] : array(), 'offset' => null );
	}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'base_id' => 'appTEST', 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK', 'duplicate_delete_enabled' => $GLOBALS['switch'] ); }
	public static function is_connected() { return $GLOBALS['connected']; }
}
class WPCPM_Students_Sync {
	const EVERY_THREE_HOURS = 'wpcpm_three_hours';
	public static function register_interval() {}
	public static function cycle_start() { return 1788998400; }
}
class WPCPM_Mentors_Sync {
	public static function tracked_statuses( $settings = null ) {
		$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
		return array( 'active' => $active, 'past' => array( 'Graduate', 'Dropped out' ), 'all' => $active );
	}
}
class WPCPM_Program {
	public static function labels() { return array( 'In Sensei' => '150h' ); }
	public static function track( $status ) { return '150h'; }
}
class WPCPM_Student_Report_Form {
	public static function fields( $track ) { return array( 'Beginner WordPress User - final grade' => array() ); }
}
class WPCPM_Mentors {
	public static function format_duration( $seconds ) { return gmdate( 'H:i:s', (int) $seconds ); }
}
class Test_WPDB {
	public $usermeta = 'wp_usermeta', $postmeta = 'wp_postmeta', $posts = 'wp_posts';
	public function prepare( $sql, $args ) { return array( $sql, (array) $args ); }
	public function get_results( $prepared, $output = null ) {
		list( $sql, $ids ) = $prepared;
		$rows = array();
		foreach ( false !== strpos( $sql, 'wp_usermeta' ) ? $GLOBALS['usermeta'] : $GLOBALS['postmeta'] as $row ) {
			if ( in_array( $row['meta_value'], $ids, true ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new Test_WPDB();

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * A record ID that says what it is.
 *
 * @param string $tag Up to fourteen letters and digits.
 * @return string
 */
function rid( $tag ) {
	return 'rec' . str_pad( strtoupper( $tag ), 14, '0' );
}

/**
 * A record in the shape the API returns.
 *
 * @param string $tag     Record ID tag.
 * @param string $created ISO 8601.
 * @param array  $fields  Cells.
 * @return array
 */
function record( $tag, $created, array $fields ) {
	return array(
		'id'          => rid( $tag ),
		'createdTime' => $created,
		'fields'      => $fields,
	);
}

/**
 * Knock on one door: run a handler or the screen, and say how it ended.
 *
 * @param callable $fn The handler.
 * @return mixed 'returned', 'die', 'redirect <url>', or the JSON a tick answered with.
 */
function door( callable $fn ) {
	$GLOBALS['nonces'] = array();
	ob_start();

	try {
		$fn();
		$out = 'returned';
	} catch ( RedirectSignal $e ) {
		$out = 'redirect ' . $e->getMessage();
	} catch ( DieSignal $e ) {
		$out = 'die';
	} catch ( JsonSignal $e ) {
		$out = $e->payload;
	}

	$GLOBALS['html'] = (string) ob_get_clean();

	return $out;
}

/**
 * The screen, as the current user sees it with this request.
 *
 * @param array $get  Query arguments.
 * @param array $post Posted fields.
 * @return string The markup.
 */
function page( array $get = array(), array $post = array() ) {
	global $finder;

	$_GET  = $get;
	$_POST = $post;
	door( array( $finder, 'render_admin_page' ) );
	$_GET  = array();
	$_POST = array();

	return $GLOBALS['html'];
}

/**
 * Whether the markup holds this text.
 *
 * @param string $html   Markup.
 * @param string $needle Text.
 * @return bool
 */
function has( $html, $needle ) {
	return false !== strpos( $html, $needle );
}

/*
 * The base: Ada is Ready (an older row in each table with nothing attached); Bo needs a decision
 * (the newest Students row did not move forward while the older one is live), and his name is
 * markup, which the screen must print as text; Cy needs a decision because the site's account
 * points at his older report row.
 */
$GLOBALS['base'] = array(
	'tblSTUDENTS' => array(
		record( 'stuold', '2026-01-27T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Full Name' => 'Ada Example', 'Status' => 'Not moving forward' ) ),
		record( 'stunew', '2026-09-09T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Full Name' => 'Ada Example', 'Status' => 'In Sensei' ) ),
		record( 'othold', '2026-02-01T10:00:00.000Z', array( 'Email' => 'bo@example.test', 'Full Name' => 'Bo <b>Example</b>', 'Status' => 'In Sensei' ) ),
		record( 'othnew', '2026-08-01T10:00:00.000Z', array( 'Email' => 'bo@example.test', 'Full Name' => 'Bo <b>Example</b>', 'Status' => 'Not moving forward' ) ),
		record( 'lkstu', '2026-03-01T10:00:00.000Z', array( 'Email' => 'cy@example.test', 'Full Name' => 'Cy Example', 'Status' => 'In Sensei' ) ),
	),
	'tblREPORTS'  => array(
		record( 'repold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example', 'Status' => 'Not moving forward' ) ),
		record( 'repnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example', 'Status' => 'In Sensei' ) ),
		record( 'lkold', '2026-03-02T10:00:00.000Z', array( 'Email' => 'cy@example.test', 'Name' => 'Cy Example', 'Status' => 'Not moving forward' ) ),
		record( 'lknew', '2026-06-02T10:00:00.000Z', array( 'Email' => 'cy@example.test', 'Name' => 'Cy Example', 'Status' => 'In Sensei' ) ),
	),
	'tblFEEDBACK' => array(
		record( 'fbold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example' ) ),
		record( 'fbnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example', 'Course' => 'In Sensei' ) ),
	),
);
$GLOBALS['usermeta'] = array( array( 'user_id' => 9, 'meta_key' => 'wpcpm_student_record_id', 'meta_value' => rid( 'lkold' ) ) );

$finder = new WPCPM_Duplicate_Finder();
$ada    = WPCPM_Duplicate_Rules::key( 'ada@example.test' );
$url    = 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder';

/* ---- the tool ------------------------------------------------------------ */

echo "\n=== A tool with a page of its own ===\n";

ck( 'the Student Duplicate Finder, at its own page slug', array( $finder->id(), $finder->label(), $finder->page_slug() ), array( 'duplicate-finder', 'Student Duplicate Finder', 'wpcpm-tool-duplicate-finder' ) );
ck( 'the Modules card says when nothing has been scanned yet', $finder->status_line(), 'No scan has run yet.' );

$finder->boot();
ck( 'boot hooks Scan now, Cancel, Delete, the progress tick, the scan, the copies and the assets', array_values( array_diff( array( 'admin_post_wpcpm_duplicates_scan_now', 'admin_post_wpcpm_duplicates_cancel', 'admin_post_wpcpm_duplicates_delete', 'wp_ajax_wpcpm_duplicates_progress', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge', 'init', 'admin_enqueue_scripts' ), array_keys( $GLOBALS['hooks'] ) ) ), array() );
ck( 'and no admin-post door for the confirmation or View copy, which are pages and change nothing', array( isset( $GLOBALS['hooks']['admin_post_wpcpm_duplicates_review'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_duplicates_view'] ) ), array( false, false ) );
ck( 'the scan is on the clock from the first boot, 150 minutes into the cycle', isset( $GLOBALS['cron']['wpcpm_duplicates_scan'] ) ? $GLOBALS['cron']['wpcpm_duplicates_scan'] : 0, 1788998400 + 150 * MINUTE_IN_SECONDS );

$finder->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-duplicate-finder' );
ck( 'its stylesheet builds on the plugin\'s admin sheet, and its script loads, on its own screen', $GLOBALS['enqueued'], array( array( 'style', 'wpcpm-duplicates', array( 'wpcpm-admin' ) ), array( 'script', 'wpcpm-duplicates', array() ) ) );
$GLOBALS['enqueued'] = array();
$finder->enqueue_assets( 'wpcredits-program_page_wpcpm-settings' );
ck( 'and on no other', $GLOBALS['enqueued'], array() );

/* ---- the doors -------------------------------------------------------------- */

echo "\n=== The capability first, then the nonce, on every door ===\n";

$GLOBALS['caps'] = false;
$shut            = array();
foreach ( array( 'handle_scan', 'handle_cancel', 'handle_delete', 'render_admin_page' ) as $method ) {
	$_POST           = array( 'wpcpm_review' => '1', 'wpcpm_students' => array( $ada ) );
	$shut[ $method ] = array( door( array( $finder, $method ) ), $GLOBALS['nonces'] );
}
$_POST              = array();
$_GET               = array( 'wpcpm_copy' => '101' );
$shut['View copy'] = array( door( array( $finder, 'render_admin_page' ) ), $GLOBALS['nonces'] );
$_GET               = array();
ck( 'without the capability every door is wp_die(), and no nonce is looked at', $shut, array_fill_keys( array( 'handle_scan', 'handle_cancel', 'handle_delete', 'render_admin_page', 'View copy' ), array( 'die', array() ) ) );

$tick = door( array( $finder, 'handle_tick' ) );
ck( 'and the progress tick answers 403 before its nonce', array( $tick['success'], $tick['code'], $GLOBALS['nonces'] ), array( false, 403, array() ) );
$GLOBALS['caps'] = true;

echo "\n=== Scan now, the progress tick and Cancel ===\n";

$GLOBALS['connected'] = false;
ck( 'Scan now with Airtable not connected: back to the screen, and no scan', array( door( array( $finder, 'handle_scan' ) ), $GLOBALS['nonces'], WPCPM_Duplicates_Scan::is_running() ), array( 'redirect ' . $url, array( 'wpcpm_duplicates_scan_now' ), false ) );
$GLOBALS['connected'] = true;

ck( 'Scan now: under its own nonce, a scan starts and the screen comes back', array( door( array( $finder, 'handle_scan' ) ), $GLOBALS['nonces'], WPCPM_Duplicates_Scan::is_running() ), array( 'redirect ' . $url, array( 'wpcpm_duplicates_scan_now' ), true ) );
ck( 'Cancel: under its own nonce, the scan stops', array( door( array( $finder, 'handle_cancel' ) ), $GLOBALS['nonces'], WPCPM_Duplicates_Scan::is_running() ), array( 'redirect ' . $url, array( 'wpcpm_duplicates_cancel' ), false ) );

door( array( $finder, 'handle_scan' ) );
$tick = door( array( $finder, 'handle_tick' ) );
ck( 'the tick runs a slice under its own nonce and answers with progress; this base takes one', array( $tick['success'], $GLOBALS['nonces'], $tick['data']['running'] ), array( true, array( 'wpcpm_duplicates_progress' ), false ) );
ck( 'and the list is written: three duplicated students, one of them Ready', array( WPCPM_Duplicates_Scan::report()['counts']['addresses'], WPCPM_Duplicates_Scan::report()['counts']['ready'] ), array( 3, 1 ) );
ck( 'which the Modules card now reads', 0 === strpos( $finder->status_line(), '3 duplicated students, read ' ), true );

/* ---- the list ------------------------------------------------------------------- */

echo "\n=== The list ===\n";

$GLOBALS['switch'] = true;
$html              = page();
$ada_box           = '<input type="checkbox" name="wpcpm_students[]" value="' . $ada . '" data-rows="students reports feedback" data-ready />';

ck( 'nothing is ticked by itself', has( $html, ' checked' ), false );
ck( 'a Ready student has one checkbox, for their older row in each table, named for what it does', array( has( $html, $ada_box ), has( $html, 'Delete the 3 older rows of Ada Example' ) ), array( true, true ) );
ck( 'and no checkbox on a row of their own', has( $html, 'value="students:' . rid( 'stuold' ) . '"' ), false );
ck( 'a student who needs a decision has a checkbox on each row that may go, named for screen readers', array( has( $html, 'name="wpcpm_rows[]" value="students:' . rid( 'othold' ) . '"' ), has( $html, 'name="wpcpm_rows[]" value="students:' . rid( 'othnew' ) . '"' ), has( $html, 'aria-label="Delete the Students row ' . rid( 'othold' ) . ' of Bo &lt;b&gt;Example&lt;/b&gt;"' ) ), array( true, true, true ) );
ck( 'a row the site points at: a disabled checkbox that points at its reason', array( has( $html, '<input type="checkbox" disabled aria-describedby="wpcpm-dup-why-' . rid( 'lkold' ) . '"' ), has( $html, '<td id="wpcpm-dup-why-' . rid( 'lkold' ) . '">The site points at it: a site account. Delete the other one instead.</td>' ) ), array( true, true ) );
ck( 'and the On the site column says what', has( $html, '<td>a site account</td>' ), true );
ck( 'every row links to its record in Airtable', has( $html, 'href="https://airtable.com/appTEST/tblREPORTS/' . rid( 'repold' ) . '"' ), true );
ck( 'the tiles: students, ready, needing a decision, and rows per table', array( has( $html, '<li>3 duplicated students</li>' ), has( $html, '<li>1 ready: every older row is a clean delete candidate</li>' ), has( $html, '<li>2 need a decision</li>' ), has( $html, '<li>Feedback: 1 proposed for deletion, 0 held</li>' ) ), array( true, true, true, true ) );
ck( 'a name is printed as text, never as markup', array( has( $html, 'Bo &lt;b&gt;Example&lt;/b&gt;' ), has( $html, '<b>Example</b>' ) ), array( true, false ) );
ck( 'Review selection posts the ticks back to this screen under the review nonce', array( has( $html, '<form method="post" action="' . $url . '" class="wpcpm-duplicates__form" data-wpcpm-duplicates>' ), has( $html, 'value="n-wpcpm_duplicates_review"' ), has( $html, '<input type="hidden" name="wpcpm_review" value="1" />' ) ), array( true, true, true ) );
ck( 'Select all ready and Clear ship hidden, for the script to show', array( has( $html, 'data-wpcpm-select-ready hidden' ), has( $html, 'data-wpcpm-clear hidden' ) ), array( true, true ) );
ck( 'the log starts empty', has( $html, 'Nothing has been deleted yet.' ), true );

$GLOBALS['switch'] = false;
$html              = page();
ck( 'deleting switched off: no checkbox at all, the list says why, and Review selection is disabled', array( has( $html, 'name="wpcpm_students[]"' ), has( $html, 'name="wpcpm_rows[]"' ), has( $html, 'Deleting is switched off, so this list is read-only.' ), has( $html, ' disabled>Review selection</button>' ) ), array( false, false, true, true ) );
$GLOBALS['switch'] = true;

WPCPM_Duplicates_Scan::start();
$html = page();
WPCPM_Duplicates_Scan::cancel();
ck( 'while a scan runs: its progress bar, the list still there, and Review selection waiting', array( has( $html, 'data-wpcpm-progress data-action="wpcpm_duplicates_progress" data-nonce="n-wpcpm_duplicates_progress"' ), has( $html, $ada_box ), has( $html, ' disabled>Review selection</button>' ) ), array( true, true, true ) );

/* ---- the confirmation --------------------------------------------------------------- */

echo "\n=== The confirmation, and Back to the list ===\n";

$chosen = array( 'wpcpm_review' => '1', 'wpcpm_students' => array( $ada ), 'wpcpm_rows' => array( 'students:' . rid( 'othold' ), 'students:' . rid( 'stunew' ) ) );
$token  = 'wpcpm_duplicates_delete_' . WPCPM_Duplicate_Finder::selection_hash( array( $ada ), array( 'students:' . rid( 'othold' ), 'students:' . rid( 'stunew' ) ) );
$html   = page( array(), $chosen );

ck( 'it is read under the review nonce', $GLOBALS['nonces'], array( 'wpcpm_duplicates_review' ) );
ck( 'it says what a press of Delete removes', has( $html, '4 rows will be deleted from Airtable: Students 2, Students Reports 1, Feedback 1. A sealed copy of each row is kept on this site for 30 days.' ), true );
ck( 'and what was left out, and why', has( $html, 'Ada Example: Students ' . rid( 'stunew' ) . ': This row cannot be deleted from the finder.' ), true );
ck( 'the red button posts to admin-post under a nonce tied to this selection', array( has( $html, 'value="n-' . $token . '"' ), has( $html, 'name="action" value="wpcpm_duplicates_delete"' ), has( $html, '<button type="submit" class="button button-primary wpcpm-duplicates__delete">Delete 4 rows from Airtable</button>' ) ), array( true, true, true ) );
ck( 'Back to the list carries the same ticks', array( has( $html, '<input type="hidden" name="wpcpm_back" value="1" />' ), substr_count( $html, '<input type="hidden" name="wpcpm_students[]" value="' . $ada . '" />' ) ), array( true, 2 ) );

$GLOBALS['switch'] = false;
$html              = page( array(), $chosen );
$GLOBALS['switch'] = true;
ck( 'deleting switched off: the button is there, disabled, with the reason', array( has( $html, 'wpcpm-duplicates__delete" disabled>' ), has( $html, 'Deleting is switched off under WPCredits Program &gt; Settings.' ) ), array( true, true ) );

$html = page( array(), array( 'wpcpm_back' => '1', 'wpcpm_students' => array( $ada ), 'wpcpm_rows' => array( 'students:' . rid( 'othold' ) ) ) );
ck( 'Back to the list: read under the review nonce, with the same boxes ticked and no others', array( $GLOBALS['nonces'], has( $html, 'data-ready checked />' ), has( $html, 'value="students:' . rid( 'othold' ) . '" data-rows="students" checked' ), has( $html, 'value="students:' . rid( 'othnew' ) . '" data-rows="students" checked' ) ), array( array( 'wpcpm_duplicates_review' ), true, true, false ) );

/* ---- delete ------------------------------------------------------------------------------ */

echo "\n=== Delete: the nonce is tied to exactly the rows listed ===\n";

$two = WPCPM_Duplicate_Finder::selection_hash( array( $ada ), array( 'students:' . rid( 'othold' ), 'reports:' . rid( 'lknew' ) ) );
ck( 'the same rows in any order are the same token', WPCPM_Duplicate_Finder::selection_hash( array( $ada ), array( 'reports:' . rid( 'lknew' ), 'students:' . rid( 'othold' ) ) ), $two );
ck( 'one row more is another', WPCPM_Duplicate_Finder::selection_hash( array( $ada ), array( 'students:' . rid( 'othold' ), 'reports:' . rid( 'lknew' ), 'students:' . rid( 'othnew' ) ) ) === $two, false );
ck( 'and a student is not a row', WPCPM_Duplicate_Finder::selection_hash( array( $ada ), array() ) === WPCPM_Duplicate_Finder::selection_hash( array(), array( $ada ) ), false );

// A user of their own, because a flash is read once per user and request.
$GLOBALS['uid']    = 21;
$GLOBALS['switch'] = false;
$_POST             = array(
	'wpcpm_students' => array( $ada, 'not-a-key', array( 'nested' ) ),
	'wpcpm_rows'     => array( 'students:' . rid( 'othold' ), 'wp_users:' . rid( 'othnew' ), 'students:recSHORT', 'students:' . rid( 'othold' ) ),
);
$out               = door( array( $finder, 'handle_delete' ) );
$_POST             = array();
ck( 'Delete checks the nonce of exactly the student keys and rows it was posted, and of nothing else', $GLOBALS['nonces'], array( 'wpcpm_duplicates_delete_' . WPCPM_Duplicate_Finder::selection_hash( array( $ada ), array( 'students:' . rid( 'othold' ) ) ) ) );
ck( 'then hands them to the delete and comes back to the screen', $out, 'redirect ' . $url );
ck( 'where the notice says what the delete answered', has( page(), 'Nothing was deleted: deleting is switched off under WPCredits Program &gt; Settings.' ), true );

$GLOBALS['uid'] = 22;
WPCPM_Flash::set(
	WPCPM_Duplicate_Finder::FLASH,
	array(
		'status'  => 'stopped',
		'deleted' => array( 'students' => 0, 'reports' => 0, 'feedback' => 1 ),
		'refused' => array( array( 'key' => $ada, 'table' => 'reports', 'id' => rid( 'repold' ), 'code' => 'site' ) ),
		'detail'  => 'Airtable asked us to wait 30 seconds before sending more requests.',
		'until'   => gmmktime( 12, 0, 0, 10, 11, 2026 ),
	)
);
$html = page();
ck( 'a stopped delete: what Airtable said, which is when to try again, what went, and until when its copy stays', has( $html, 'Airtable stopped the delete part of the way through: Airtable asked us to wait 30 seconds before sending more requests. Deleted 1 row: Students 0, Students Reports 0, Feedback 1. A sealed copy of each is kept until 11 October 2026. Rows it did not confirm are checked again by the daily run.' ), true );
ck( 'and each row it left out, with why', has( $html, '<li>Students Reports ' . rid( 'repold' ) . ': The site points at it now.</li>' ), true );
$GLOBALS['uid']    = 1;
$GLOBALS['switch'] = true;

/* ---- the log and View copy ------------------------------------------------------------------ */

echo "\n=== The log and View copy ===\n";

$kept = WPCPM_Duplicate_Vault::keep( 'students', record( 'gone', '2026-01-05T10:00:00.000Z', array( 'Email' => 'gone@example.test', 'Full Name' => 'Gone Example', 'Status' => 'Not moving forward' ) ), 7, time() );
WPCPM_Duplicate_Vault::confirm( array( $kept ) );
WPCPM_Duplicate_Vault::keep( 'feedback', record( 'wait', '2026-01-06T10:00:00.000Z', array( 'Email' => 'wait@example.test', 'Name' => 'Wait Example' ) ), 7, time() );

$html = page();
$log  = substr( $html, (int) strpos( $html, '<h2>Deleted rows</h2>' ) );
ck( 'the log: when, who, the table, the record, the created date and the status', has( $log, '<td>Pat Manager</td><td>Students</td><td><code>' . rid( 'gone' ) . '</code></td><td>2026-01-05</td><td>Not moving forward</td>' ), true );
ck( 'View copy, behind a nonce keyed to the copy', has( $log, 'href="' . $url . '&wpcpm_copy=' . $kept . '&_wpnonce=n-wpcpm_duplicates_view_' . $kept . '"' ), true );
ck( 'a delete Airtable did not confirm says so', has( $log, 'Airtable did not confirm this delete; the daily run checks it again.' ), true );
ck( 'and the log holds no name and no address', array( has( $log, 'Gone Example' ), has( $log, 'gone@example.test' ), has( $log, 'wait@example.test' ) ), array( false, false, false ) );

$html = page( array( 'wpcpm_copy' => (string) $kept ) );
ck( 'View copy: under its own nonce, the row whole, for typing back by hand', array( $GLOBALS['nonces'], has( $html, '<th scope="row">Email</th><td>gone@example.test</td>' ), has( $html, 'There is no automatic restore' ) ), array( array( 'wpcpm_duplicates_view_' . $kept ), true, true ) );

WPCPM_Duplicate_Vault::purge( static function () { return null; }, time() + 31 * DAY_IN_SECONDS );
$html = page( array( 'wpcpm_copy' => (string) $kept ) );
ck( 'and once erased, it opens no more', array( has( $html, 'This copy has been erased: copies are kept for 30 days.' ), has( $html, 'gone@example.test' ) ), array( true, false ) );
ck( 'while the log keeps the entry', has( page(), 'Erased after 30 days.' ), true );

foreach ( array( 'includes/tools/class-wpcpm-duplicate-finder.php', 'includes/tools/class-wpcpm-duplicate-finder-screen.php', 'assets/js/duplicate-finder.js', 'assets/css/duplicate-finder.css' ) as $file ) {
	ck( 'no dash but the plain hyphen in ' . $file, 1 === preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) ), false );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

and apply:

```diff
--- a/bin/test-roles.php
+++ b/bin/test-roles.php
@@ -361,6 +361,13 @@ foreach ( array( 'includes', 'includes/modules', 'includes/tools', 'includes/tra
 ck( 'every class file under includes/ is required by the loader',
     array_values( array_diff( $on_disk, $anywhere[1] ) ), array() );
 
+// A tool reaches the menu, the Modules screen and the uninstall fan-out through the registry and
+// nowhere else, so a tool that loads and is never registered is a screen nobody can open and data
+// an uninstall leaves behind. Read from the source, because instantiating the registry here would
+// need every tool's dependencies (the Student Duplicate Finder, 1.102.0).
+ck( 'the Student Duplicate Finder is registered as a tool',
+    array( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpcpm-tools.php' ), 'new WPCPM_Duplicate_Finder()' ) ), array( true ) );
+
 // The institution dashboard's own wiring, asserted from the day the file exists rather than
 // from a name written here in advance. Its page ID and its title-version flag are two options
 // on a live site, and an option an uninstall leaves behind is invisible until somebody
```

```diff
--- a/bin/test-handlers.php
+++ b/bin/test-handlers.php
@@ -545,5 +545,23 @@ run( 'handle_approve (no capability)', array( 'WPCPM_Sponsor_Application', 'hand
 
 $GLOBALS['caps'] = true;
 
+echo "\n=== WPCPM_Duplicate_Finder (1.102.0) ===\n";
+
+// Scan now with Airtable not connected (the fixture's settings hold no token), Cancel with no scan
+// running, and Delete with nothing ticked while deleting is switched off: the shortest path to
+// each redirect. Then Delete without the capability, which meets wp_die() before its nonce.
+$finder = new WPCPM_Duplicate_Finder();
+$_POST  = array();
+
+run( 'handle_scan (Airtable not connected)', array( $finder, 'handle_scan' ) );
+run( 'handle_cancel (no scan running)', array( $finder, 'handle_cancel' ) );
+run( 'handle_delete (nothing ticked, switched off)', array( $finder, 'handle_delete' ) );
+
+$GLOBALS['caps'] = false;
+
+run( 'handle_delete (no capability)', array( $finder, 'handle_delete' ) );
+
+$GLOBALS['caps'] = true;
+
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL HANDLERS REACHED A NORMAL OUTCOME\n" );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-duplicate-finder.php`, `php bin/test-roles.php` and `php bin/test-handlers.php`. Expected:

```text
PHP Warning:  require_once(includes/tools/class-wpcpm-duplicate-finder-screen.php): Failed to open stream: No such file or directory in bin/test-duplicate-finder.php on line 228
PHP Fatal error:  Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-duplicate-finder-screen.php' (include_path='...') in bin/test-duplicate-finder.php:228
```

```text
FAIL the Student Duplicate Finder is registered as a tool
```

```text
PHP Fatal error:  Uncaught Error: Class "WPCPM_Duplicate_Finder" not found in bin/test-handlers.php:553
```

- [ ] **Step 3: Write the screen, the tool and their assets.** Create `includes/tools/class-wpcpm-duplicate-finder-screen.php`:

```php
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
					printf( '<td><input type="checkbox" disabled aria-describedby="%1$s" aria-label="%2$s" /></td>', esc_attr( $reason ), esc_attr__( 'Cannot be deleted', 'wpcredits-program-manager' ) );
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
```

Create `includes/tools/class-wpcpm-duplicate-finder.php`:

```php
<?php
/**
 * Student Duplicate Finder.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The tool: the scan's controls, the list, the confirmation, and the delete.
 *
 * A tool and not a module (spec decision 3.1): it owns an operation, not an audience, and on
 * screen it sits under Modules with the other tools. Every screen and every handler is behind
 * `WPCPM_Roles::CAP_MANAGE`.
 *
 * The flow is two deliberate steps by one manager (the owner's answer of 11 September 2026):
 * tick rows, press Review selection, read the confirmation, press Delete. The confirmation is a
 * page and not a change, so it is posted to this screen; the delete is a change, so it goes to
 * `admin-post.php`, under a nonce tied to exactly the rows the confirmation listed.
 *
 * **The stored report is the menu, not the authority** (decision 3.6). The delete handler reads
 * the selected rows and their siblings from Airtable again, asks the database again what the site
 * points at, and asks `WPCPM_Duplicate_Rules::recheck()` again, before it keeps a sealed copy of
 * each row (decision 3.7) and deletes, children first (decision 3.8).
 *
 * The three sync handlers are copied from `WPCPM_Sync_Module`, in its order (the capability, then
 * the nonce), because that class serves modules and this is a tool (decision 3.9).
 */
class WPCPM_Duplicate_Finder extends WPCPM_Tool {

	const ACTION_SCAN   = 'wpcpm_duplicates_scan_now';
	const ACTION_CANCEL = 'wpcpm_duplicates_cancel';
	const ACTION_TICK   = 'wpcpm_duplicates_progress';
	const ACTION_REVIEW = 'wpcpm_duplicates_review';
	const ACTION_DELETE = 'wpcpm_duplicates_delete';
	const ACTION_VIEW   = 'wpcpm_duplicates_view';

	/** Flash channel for this screen's outcomes. */
	const FLASH = 'duplicates';

	/** A student in a posted form: `WPCPM_Duplicate_Rules::key()`'s sixteen hex characters. */
	const KEY_PATTERN = '/^[0-9a-f]{16}$/D';

	/** A row in a posted form: its table, a colon, its record ID. */
	const PAIR_PATTERN = '/^(students|reports|feedback):rec[A-Za-z0-9]{14}$/D';

	/**
	 * Tool identifier.
	 *
	 * @return string
	 */
	public function id() {
		return 'duplicate-finder';
	}

	/**
	 * Human-readable tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Student Duplicate Finder', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Modules screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Finds students who have more than one row in Students, Students Reports or Feedback, and deletes the older rows a program manager selects and confirms.', 'wpcredits-program-manager' );
	}

	/**
	 * The last scan, in a line.
	 *
	 * @return string
	 */
	public function status_line() {
		$report = WPCPM_Duplicates_Scan::report();

		if ( ! $report ) {
			return __( 'No scan has run yet.', 'wpcredits-program-manager' );
		}

		$count = (int) $report['counts']['addresses'];

		return sprintf(
			/* translators: 1: number of duplicated students, 2: date and time of the scan. */
			_n( '%1$s duplicated student, read %2$s.', '%1$s duplicated students, read %2$s.', $count, 'wpcredits-program-manager' ),
			number_format_i18n( $count ),
			wp_date( 'Y-m-d H:i', (int) $report['read'] )
		);
	}

	/**
	 * Hooks. The schedules are put on the clock here and not only at activation, because sites
	 * update by dropping in files and never re-activate.
	 */
	public function boot() {
		WPCPM_Duplicates_Scan::register_cron();

		add_action( 'init', array( 'WPCPM_Duplicate_Vault', 'register' ) );
		add_action( 'init', array( 'WPCPM_Duplicate_Vault', 'schedule' ), 20 );
		add_action( WPCPM_Duplicate_Vault::CRON_PURGE, array( 'WPCPM_Duplicate_Vault', 'purge' ), 10, 0 );

		add_action( 'admin_post_' . self::ACTION_SCAN, array( $this, 'handle_scan' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Activation: the scan's schedule.
	 */
	public function activate() {
		WPCPM_Duplicates_Scan::activate();
	}

	/**
	 * Deactivation: both schedules off the clock. The report and the copies stay.
	 */
	public function deactivate() {
		WPCPM_Duplicates_Scan::deactivate();
		WPCPM_Duplicate_Vault::deactivate();
	}

	/**
	 * Uninstall: the report, the schedules, and every copy with its log entry.
	 */
	public function uninstall() {
		WPCPM_Duplicates_Scan::uninstall();
		WPCPM_Duplicate_Vault::delete_all();
	}

	/**
	 * The screen's own stylesheet and script, on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, $this->page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'wpcpm-duplicates', WPCPM_PLUGIN_URL . 'assets/css/duplicate-finder.css', array( 'wpcpm-admin' ), WPCPM_VERSION );
		wp_enqueue_script( 'wpcpm-duplicates', WPCPM_PLUGIN_URL . 'assets/js/duplicate-finder.js', array(), WPCPM_VERSION, true );
	}

	/**
	 * Start a scan now.
	 */
	public function handle_scan() {
		$this->verify( self::ACTION_SCAN );

		$result = WPCPM_Duplicates_Scan::start();

		$this->redirect_back( array( 'status' => is_wp_error( $result ) ? 'error' : 'started' ) );
	}

	/**
	 * Cancel the scan in progress. The last good list stays.
	 */
	public function handle_cancel() {
		$this->verify( self::ACTION_CANCEL );

		WPCPM_Duplicates_Scan::cancel();

		$this->redirect_back( array( 'status' => 'cancelled' ) );
	}

	/**
	 * The AJAX tick behind the progress bar: run a slice if a scan is on, answer with progress.
	 */
	public function handle_tick() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( self::ACTION_TICK, 'nonce' );

		if ( WPCPM_Duplicates_Scan::is_running() ) {
			WPCPM_Duplicates_Scan::run_tick( WPCPM_Duplicates_Scan::BUDGET_AJAX );
		}

		wp_send_json_success( WPCPM_Duplicates_Scan::progress() );
	}

	/**
	 * Delete what the confirmation listed, after asking every question again (spec 7.3).
	 */
	public function handle_delete() {
		$keys  = WPCPM_Request::posted_list( 'wpcpm_students', self::KEY_PATTERN );
		$pairs = WPCPM_Request::posted_list( 'wpcpm_rows', self::PAIR_PATTERN );

		// The nonce is tied to exactly this set of rows, so a token taken from one confirmation
		// cannot delete a different set (spec 7.2).
		$this->verify( self::ACTION_DELETE . '_' . self::selection_hash( $keys, $pairs ) );

		$this->redirect_back( WPCPM_Duplicate_Delete::run( $keys, $pairs, get_current_user_id() ) );
	}

	/**
	 * The screen: a copy, the confirmation, or the list.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$copy = WPCPM_Request::id( 'wpcpm_copy' );

		if ( $copy > 0 ) {
			$this->render_copy_view( $copy );
			return;
		}

		if ( '' !== WPCPM_Request::posted_key( 'wpcpm_review' ) ) {
			$this->render_confirm_view();
			return;
		}

		WPCPM_Duplicate_Finder_Screen::render_list(
			array(
				'report'   => WPCPM_Duplicates_Scan::report(),
				'progress' => WPCPM_Duplicates_Scan::progress(),
				'last'     => WPCPM_Duplicates_Scan::last_read(),
				'next'     => (int) wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_SCAN ),
				'enabled'  => ! empty( WPCPM_Settings::get()['duplicate_delete_enabled'] ),
				'log'      => WPCPM_Duplicate_Vault::entries( 50 ),
				'flash'    => WPCPM_Flash::take( self::FLASH ),
				'url'      => $this->admin_url(),
				'checked'  => $this->posted_back(),
			)
		);
	}

	/**
	 * The key a confirmation's nonce is tied to: the selection it lists, in a fixed order.
	 *
	 * @param string[] $keys  Student keys.
	 * @param string[] $pairs `table:record` pairs.
	 * @return string Sixteen hex characters.
	 */
	public static function selection_hash( array $keys, array $pairs ) {
		sort( $keys );
		sort( $pairs );

		return substr( md5( implode( ',', $keys ) . '|' . implode( ',', $pairs ) ), 0, 16 );
	}

	/**
	 * The confirmation: what a press of Delete would do, for the manager to read.
	 */
	private function render_confirm_view() {
		check_admin_referer( self::ACTION_REVIEW );

		$keys  = WPCPM_Request::posted_list( 'wpcpm_students', self::KEY_PATTERN );
		$pairs = WPCPM_Request::posted_list( 'wpcpm_rows', self::PAIR_PATTERN );

		WPCPM_Duplicate_Finder_Screen::render_confirm(
			array(
				'report'  => WPCPM_Duplicates_Scan::report(),
				'chosen'  => WPCPM_Duplicate_Rules::expand( WPCPM_Duplicates_Scan::report(), $keys, $pairs ),
				'keys'    => $keys,
				'pairs'   => $pairs,
				'nonce'   => self::ACTION_DELETE . '_' . self::selection_hash( $keys, $pairs ),
				'enabled' => ! empty( WPCPM_Settings::get()['duplicate_delete_enabled'] ),
				'running' => WPCPM_Duplicates_Scan::is_running(),
				'url'     => $this->admin_url(),
			)
		);
	}

	/**
	 * One copy, unsealed, behind a nonce tied to it.
	 *
	 * @param int $copy The copy's post ID.
	 */
	private function render_copy_view( $copy ) {
		check_admin_referer( self::ACTION_VIEW . '_' . (int) $copy );

		WPCPM_Duplicate_Finder_Screen::render_copy( (int) $copy, WPCPM_Duplicate_Vault::view( (int) $copy ), $this->admin_url() );
	}

	/**
	 * The ticks Back to the list carries, so the manager returns to the selection they reviewed.
	 *
	 * @return array `keys` and `pairs`, or nothing when the list was not reached from the confirmation.
	 */
	private function posted_back() {
		if ( '' === WPCPM_Request::posted_key( 'wpcpm_back' ) ) {
			return array();
		}

		check_admin_referer( self::ACTION_REVIEW );

		return array(
			'keys'  => WPCPM_Request::posted_list( 'wpcpm_students', self::KEY_PATTERN ),
			'pairs' => WPCPM_Request::posted_list( 'wpcpm_rows', self::PAIR_PATTERN ),
		);
	}

	/**
	 * The capability first, then the nonce, then nothing else.
	 *
	 * @param string $action The nonce action.
	 */
	private function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Back to the screen, with what happened flashed for the person who pressed.
	 *
	 * @param array $outcome `status`, and what the notice says beside it.
	 */
	private function redirect_back( array $outcome ) {
		WPCPM_Flash::set( self::FLASH, $outcome );
		wp_safe_redirect( $this->admin_url() );
		exit;
	}
}
```

Create `assets/js/duplicate-finder.js`:

```js
/**
 * Student Duplicate Finder: the live count, Select all ready and Clear.
 *
 * The form works without this. It only counts the rows the ticked boxes stand for, in the words
 * the bar's data-template gives, and shows the two buttons, which ship hidden so that nothing on
 * the page does nothing when scripts are off.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-wpcpm-duplicates]' );

	if ( ! form ) {
		return;
	}

	var count = form.querySelector( '[data-wpcpm-selection-count]' );
	var ready = form.querySelector( '[data-wpcpm-select-ready]' );
	var clear = form.querySelector( '[data-wpcpm-clear]' );
	var submit = form.querySelector( '[data-wpcpm-selection] button[type="submit"]' );

	function boxes() {
		return Array.prototype.slice.call( form.querySelectorAll( 'input[type="checkbox"][data-rows]' ) );
	}

	function update() {
		var tally = { students: 0, reports: 0, feedback: 0 };
		var total = 0;

		boxes().forEach( function ( box ) {
			if ( ! box.checked ) {
				return;
			}

			( box.getAttribute( 'data-rows' ) || '' ).split( ' ' ).forEach( function ( table ) {
				if ( Object.prototype.hasOwnProperty.call( tally, table ) ) {
					tally[ table ]++;
					total++;
				}
			} );
		} );

		if ( count && count.getAttribute( 'data-template' ) ) {
			count.textContent = count.getAttribute( 'data-template' )
				.replace( '%1$s', String( total ) )
				.replace( '%2$s', String( tally.students ) )
				.replace( '%3$s', String( tally.reports ) )
				.replace( '%4$s', String( tally.feedback ) );
		}

		if ( submit ) {
			submit.disabled = 0 === total;
		}
	}

	if ( ready ) {
		ready.hidden = false;
		ready.addEventListener( 'click', function () {
			form.querySelectorAll( 'input[data-ready]' ).forEach( function ( box ) {
				box.checked = true;
			} );
			update();
		} );
	}

	if ( clear ) {
		clear.hidden = false;
		clear.addEventListener( 'click', function () {
			boxes().forEach( function ( box ) {
				box.checked = false;
			} );
			update();
		} );
	}

	form.addEventListener( 'change', update );

	if ( ready ) {
		update();
	}
}() );
```

Create `assets/css/duplicate-finder.css`:

```css
/**
 * Student Duplicate Finder.
 *
 * Built on assets/css/admin.css (the cards, the progress bar), which the screen loads first.
 */

.wpcpm-duplicates__tiles {
	display: grid;
	grid-template-columns: repeat( auto-fit, minmax( 190px, 1fr ) );
	gap: 12px;
	margin: 16px 0;
	padding: 0;
	list-style: none;
}

.wpcpm-duplicates__tiles li {
	margin: 0;
	padding: 12px 16px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
}

.wpcpm-duplicates__group {
	margin: 0 0 16px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
}

.wpcpm-duplicates__head {
	display: flex;
	flex-wrap: wrap;
	gap: 8px 16px;
	align-items: baseline;
	padding: 12px 16px;
	border-bottom: 1px solid #dcdcde;
}

.wpcpm-duplicates__head h3,
.wpcpm-duplicates__pick {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
}

.wpcpm-duplicates__email,
.wpcpm-duplicates__counts {
	color: #50575e;
}

.wpcpm-duplicates__flag {
	margin: 0;
	padding: 8px 16px;
	background: #fcf9e8;
	border-bottom: 1px solid #dcdcde;
}

/* Wide tables scroll inside their card, never the page. */
.wpcpm-duplicates__scroll {
	overflow-x: auto;
}

.wpcpm-duplicates__scroll table {
	min-width: 960px;
	border: 0;
}

.wpcpm-duplicates__badge {
	display: inline-block;
	padding: 2px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
}

.wpcpm-duplicates__badge--keep,
.wpcpm-duplicates__badge--ready {
	background: #008a20;
	color: #fff;
}

.wpcpm-duplicates__badge--delete {
	background: #d63638;
	color: #fff;
}

.wpcpm-duplicates__badge--review,
.wpcpm-duplicates__badge--decide {
	background: #f0c33c;
	color: #1e1e1e;
}

/* The selection bar stays in reach at the foot of a long list. */
.wpcpm-duplicates__bar {
	position: sticky;
	bottom: 0;
	z-index: 1;
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	margin: 16px 0;
	padding: 12px 16px;
	background: #fff;
	border: 1px solid #dcdcde;
	box-shadow: 0 -2px 6px rgba( 0, 0, 0, 0.06 );
}

.wpcpm-duplicates__count {
	flex: 1 1 280px;
	margin: 0;
}

.wpcpm-duplicates__actions {
	display: flex;
	gap: 8px;
	margin: 16px 0;
}

.wpcpm-duplicates__inline {
	display: inline;
}

.wp-core-ui .button-primary.wpcpm-duplicates__delete {
	background: #b32d2e;
	border-color: #b32d2e;
}

.wp-core-ui .button-primary.wpcpm-duplicates__delete:hover,
.wp-core-ui .button-primary.wpcpm-duplicates__delete:focus {
	background: #8a2424;
	border-color: #8a2424;
}

.wpcpm-duplicates__refused {
	margin: 0 0 8px 20px;
	list-style: disc;
}
```

Register the tool and load both classes:

```diff
--- a/includes/class-wpcpm-tools.php
+++ b/includes/class-wpcpm-tools.php
@@ -28,7 +28,7 @@ class WPCPM_Tools {
 	 */
 	public static function all() {
 		if ( null === self::$tools ) {
-			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker() );
+			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder() );
 
 			/**
 			 * Filter the registered tools.
```

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -138,6 +138,8 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php'
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
```

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -144,6 +144,8 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicates-scan.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-vault.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-delete.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-finder.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
 WPCPM_Modules::uninstall();
```

- [ ] **Step 4: Run them and see them pass.** Expected last lines: `ALL PASS (55 checks)`, `ALL PASS` and `ALL HANDLERS REACHED A NORMAL OUTCOME`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.` `phpcs` on the two new classes alone prints nothing.

- [ ] **Step 6: Commit.**

```bash
git add assets/css/duplicate-finder.css assets/js/duplicate-finder.js bin/test-duplicate-finder.php bin/test-handlers.php bin/test-roles.php includes/class-wpcpm-tools.php includes/tools/class-wpcpm-duplicate-finder-screen.php includes/tools/class-wpcpm-duplicate-finder.php uninstall.php wpcredits-program-manager.php
git commit -m "Student Duplicate Finder: the screen, where a manager ticks, reviews and confirms, and the log of what went"
```

---

### Task 9: The Administrator Dashboard's tile and card

Spec section 9: a thirteenth tile, Duplicated students, whose number is the last report's address count, muted at zero like the others, anchored to a small card that says how many are Ready and as of when, and links to the finder. The card reads through the scan's `report()`, as every card reads through the class that owns its data, and takes the counts only: the names stay on the finder's screen. It is drawn with the cards' own pieces (`card_open()`, `empty_line()`, the note and the secondary button), so it copies their rules value for value.

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`collect()`, `counts()`, the new `duplicates()` and `render_duplicates()`), `includes/modules/class-wpcpm-administrators-dashboard.php` (the card, after Offers running low), `includes/class-wpcpm-return.php` (the anchor)
- Test: `bin/test-administrators-dashboard.php`, `bin/test-return.php`

**Interfaces:**
- Consumes: Task 4's `report()`.
- Produces: `WPCPM_Administrators_Cards::duplicates()` (`addresses`, `ready`, `read` and `url`) and `render_duplicates( array $facts )`; `counts()` answers a thirteenth tile, `duplicates`, with the card `duplicates`; `WPCPM_Return::ANCHORS` names `duplicates` after `offers-low`.

- [ ] **Step 1: Write the failing checks.** Apply:

```diff
--- a/bin/test-return.php
+++ b/bin/test-return.php
@@ -68,7 +68,7 @@ ck( 'and nothing for an empty target', printed( '' ), '' );
 $html = printed( 'dashboard', 'requests' );
 ck( 'and both inputs for the dashboard', false !== strpos( $html, 'name="wpcpm_return" value="dashboard"' ) && false !== strpos( $html, 'name="wpcpm_return_to" value="requests"' ), true );
 ck( 'an unknown anchor is not printed', false !== strpos( printed( 'dashboard', 'evil' ), 'wpcpm_return_to' ), false );
-ck( 'the anchors are the twelve cards and the strip', WPCPM_Return::ANCHORS, array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'interests', 'sponsors', 'programs', 'health' ) );
+ck( 'the anchors are the thirteen cards and the strip', WPCPM_Return::ANCHORS, array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'duplicates', 'interests', 'sponsors', 'programs', 'health' ) );
 
 // Read from the cards rather than from a copy of the list: card_open()'s own contract says its
 // id is one of these, and three of the twelve ids - offers-low, interests and sponsors - were
```

```diff
--- a/bin/test-administrators-dashboard.php
+++ b/bin/test-administrators-dashboard.php
@@ -576,6 +576,11 @@ class WPCPM_Sponsors_Sync {
 	public static function progress() { return isset( $GLOBALS['sync']['sponsors'] ) ? $GLOBALS['sync']['sponsors'] : array( 'running' => false, 'phase' => '', 'label' => '', 'error' => '', 'elapsed' => 0 ); }
 	public static function last_read() { return 1756880000; }
 }
+// The Student Duplicate Finder's scan, as the thirteenth tile and its card read it (1.102.0): the
+// stored report, of which the dashboard reads the counts and the read time and nothing else.
+class WPCPM_Duplicates_Scan {
+	public static function report() { return isset( $GLOBALS['dup_report'] ) ? $GLOBALS['dup_report'] : array(); }
+}
 if ( ! function_exists( 'size_format' ) ) { function size_format( $b, $d = 0 ) { return $b . ' B'; } }
 
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php';
@@ -740,6 +745,10 @@ $GLOBALS['audit'] = array(
 	array( 'id' => 9103, 'kind' => 'member_added', 'sponsor' => 'recSPN00000000001', 'message' => 'Someone joined', 'time' => time() - DAY_IN_SECONDS ),
 );
 
+// The Student Duplicate Finder's last scan: three duplicated students, one of them Ready, and the
+// groups the finder's screen lists left out, because the dashboard never reads them.
+$GLOBALS['dup_report'] = array( 'v' => 1, 'read' => 1757000000, 'counts' => array( 'addresses' => 3, 'ready' => 1, 'decide' => 2 ) );
+
 /* ---- collect() and counts() ---------------------------------------------- */
 
 echo "=== The data is read once, through the owners ===\n";
@@ -759,14 +768,14 @@ ck( 'two open requests, one overdue, one closed', array( count( $data['requests'
 ck( 'one locked account', count( $data['locked'] ), 1 );
 
 $counts = WPCPM_Administrators_Cards::counts( $data );
-ck( 'twelve tiles in the spec\'s order, the sponsor tiles last', array_keys( $counts ), array( 'applications', 'agreements', 'overdue_agreements', 'drafts', 'due', 'requests', 'overdue_requests', 'locked', 'sponsor_posts', 'sponsor_agreements', 'sponsor_applications', 'offers_low' ) );
+ck( 'thirteen tiles in the spec\'s order, the sponsor tiles and then the duplicated students last', array_keys( $counts ), array( 'applications', 'agreements', 'overdue_agreements', 'drafts', 'due', 'requests', 'overdue_requests', 'locked', 'sponsor_posts', 'sponsor_agreements', 'sponsor_applications', 'offers_low', 'duplicates' ) );
 // array_map() keeps the input array's keys, and counts() is keyed by tile name (the
 // previous check pins that order), so the expectation is keyed the same way rather than
 // the plain list the brief first wrote, which could never === an array with string keys.
-ck( 'each tile is a number and a card', array_map( static function ( $t ) { return $t['n'] . ':' . $t['card']; }, $counts ), array( 'applications' => '2:applications', 'agreements' => '2:agreements', 'overdue_agreements' => '1:agreements', 'drafts' => '1:reports', 'due' => '1:reports', 'requests' => '2:requests', 'overdue_requests' => '1:requests', 'locked' => '1:health', 'sponsor_posts' => '1:sponsor-posts', 'sponsor_agreements' => '1:sponsor-agreements', 'sponsor_applications' => '1:sponsor-applications', 'offers_low' => '1:offers-low' ) );
+ck( 'each tile is a number and a card', array_map( static function ( $t ) { return $t['n'] . ':' . $t['card']; }, $counts ), array( 'applications' => '2:applications', 'agreements' => '2:agreements', 'overdue_agreements' => '1:agreements', 'drafts' => '1:reports', 'due' => '1:reports', 'requests' => '2:requests', 'overdue_requests' => '1:requests', 'locked' => '1:health', 'sponsor_posts' => '1:sponsor-posts', 'sponsor_agreements' => '1:sponsor-agreements', 'sponsor_applications' => '1:sponsor-applications', 'offers_low' => '1:offers-low', 'duplicates' => '3:duplicates' ) );
 
 echo "\n=== The sponsors' figures, the pools running low and the new interests (S6) ===\n";
-ck( 'twelve tiles now, offers running low last', array( count( $counts ), array_slice( array_keys( $counts ), -2 ), $counts['offers_low'] ), array( 12, array( 'sponsor_applications', 'offers_low' ), array( 'label' => 'Offers running low', 'n' => 1, 'card' => 'offers-low' ) ) );
+ck( 'thirteen tiles now, offers running low just before the duplicated students', array( count( $counts ), array_slice( array_keys( $counts ), -3 ), $counts['offers_low'] ), array( 13, array( 'sponsor_applications', 'offers_low', 'duplicates' ), array( 'label' => 'Offers running low', 'n' => 1, 'card' => 'offers-low' ) ) );
 ck( 'one pool runs low: live, of kind codes, under its own threshold; the expired, ended, shared and well-stocked ones are not it', array_map( static function ( $r ) { return $r['id'] . ':' . $r['available'] . '/' . $r['low'] . ':' . $r['sponsor_name']; }, $data['offers_low'] ), array( '941:3/10:TEST Sponsor' ) );
 ck( 'and it links to the sponsor\'s Offers and codes card through the switcher', $data['offers_low'][0]['url'], 'https://site.example/sponsor-dashboard/?wpcpm_sponsor_view=recSPN00000000001#wpcpm-sponsor-offers' );
 ck( 'one interest in the last thirty days, of the audit kind sponsor_interest only, with the sponsor\'s name and the line as written', array( count( $data['interests'] ), $data['interests'][0]['sponsor_name'], $data['interests'][0]['message'], $data['interests'][0]['url'] ), array( 1, 'TEST Sponsor', '2026-09-01 by Member One: Sponsor a mentor or multiple mentors; events: WordCamp Europe; note: "Two mentors from Q1."', 'https://site.example/sponsor-dashboard/?wpcpm_sponsor_view=recSPN00000000001#wpcpm-sponsor-interests' ) );
@@ -795,6 +804,15 @@ $strip2 = capture( static function () use ( $data ) { WPCPM_Administrators_Cards
 // hardcoded digit here would silently pin the wrong one (brief review).
 ck( 'the Sponsors card is four tiles on the programs card\'s markup: a name, the number and a qualifier each, never the name twice', array( has( $strip2, 'id="wpcpm-sponsors"' ), substr_count( $strip2, '<li class="wpcpm-programs__tile">' ), preg_match( '#<span class="wpcpm-programs__name">Approved sponsors</span><span class="wpcpm-programs__n">2</span><span class="wpcpm-programs__l">in the program records</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">With an account</span><span class="wpcpm-programs__n">1</span><span class="wpcpm-programs__l">of the Approved sponsors</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">Live offers</span><span class="wpcpm-programs__n">3</span><span class="wpcpm-programs__l">shown on the site today</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">Claims this semester</span><span class="wpcpm-programs__n">' . (int) $data['sponsors']['claims_semester'] . '</span><span class="wpcpm-programs__l">since ' . preg_quote( $data['sponsors']['since_display'], '#' ) . ', on every offer</span>#', $strip2 ) ), array( true, 4, 1, 1, 1, 1 ) );
 
+echo "\n=== Duplicated students: the thirteenth tile and its card (1.102.0) ===\n";
+
+ck( 'the tile reads the Student Duplicate Finder\'s last scan through the scan\'s own report', array( $data['duplicates'], $counts['duplicates'] ), array( array( 'addresses' => 3, 'ready' => 1, 'read' => 1757000000, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder' ), array( 'label' => 'Duplicated students', 'n' => 3, 'card' => 'duplicates' ) ) );
+$dup = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( $data['duplicates'] ); } );
+ck( 'the card: open with its count, how many are Ready and as of when, and the way to the finder', array( has( $dup, 'id="wpcpm-duplicates"' ), has( $dup, 'wpcpm-group__disclosure" open' ), has( $dup, '<span class="wpcpm-group__count">3</span>' ), has( $dup, '3 duplicated students in Airtable, 1 ready to delete, as of ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), 1757000000 ) . '.' ), has( $dup, '<a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder">Open the Student Duplicate Finder</a>' ) ), array( true, true, true, true, true ) );
+$clean = capture( static function () { WPCPM_Administrators_Cards::render_duplicates( array( 'addresses' => 0, 'ready' => 0, 'read' => 1757000000, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder' ) ); } );
+$never = capture( static function () { WPCPM_Administrators_Cards::render_duplicates( array() ); } );
+ck( 'a clean scan says so, folded, and so does a finder that has not scanned yet', array( has( $clean, 'No duplicated students in Airtable, as of ' ), has( $clean, '<details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure">' ), has( $never, 'The Student Duplicate Finder has not scanned Airtable yet.' ) ), array( true, true, true ) );
+
 /* ---- programs() ---------------------------------------------------------- */
 
 echo "\n=== Programs running ===\n";
@@ -842,14 +860,14 @@ $strip = capture( static function () use ( $counts ) { WPCPM_Administrators_Card
 // Eight, counting the opening tag rather than the bare class: the wrapping
 // <ul class="wpcpm-attention__tiles"> also matches the bare needle, since "tiles" starts with
 // "tile", so the bare count would read nine and call the wrapper a ninth tile.
-ck( 'the strip is one section with twelve tiles linking to the cards', array( substr_count( $strip, '<li class="wpcpm-attention__tile' ), has( $strip, 'id="wpcpm-attention"' ), has( $strip, 'href="#wpcpm-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-posts"' ), has( $strip, 'href="#wpcpm-sponsor-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-applications"' ) ), array( 12, true, true, true, true, true ) );
+ck( 'the strip is one section with thirteen tiles linking to the cards', array( substr_count( $strip, '<li class="wpcpm-attention__tile' ), has( $strip, 'id="wpcpm-attention"' ), has( $strip, 'href="#wpcpm-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-posts"' ), has( $strip, 'href="#wpcpm-sponsor-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-applications"' ), has( $strip, 'href="#wpcpm-duplicates"' ) ), array( 13, true, true, true, true, true, true ) );
 // None of the fixture's eight counts is 0, so this used to hold no matter what render_strip()
 // did with a zero; a tile is zeroed here, from counts()'s own output, so the check can
 // actually fail if --zero ever stops being drawn (final review, Important 6).
 $zero_counts = $counts;
 $zero_counts['locked']['n'] = 0;
 $zero_strip = capture( static function () use ( $zero_counts ) { WPCPM_Administrators_Cards::render_strip( $zero_counts ); } );
-ck( 'a zero is drawn muted, not hidden', array( substr_count( $zero_strip, 'wpcpm-attention__tile--zero' ), substr_count( $zero_strip, '<li' ) ), array( 1, 12 ) );
+ck( 'a zero is drawn muted, not hidden', array( substr_count( $zero_strip, 'wpcpm-attention__tile--zero' ), substr_count( $zero_strip, '<li' ) ), array( 1, 13 ) );
 
 $apps = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); } );
 ck( 'the applications card is open with its count', has( $apps, 'id="wpcpm-applications"' ) && has( $apps, 'wpcpm-group__disclosure" open' ) && has( $apps, '<span class="wpcpm-group__count">2</span>' ), true );
@@ -1015,7 +1033,7 @@ ck( 'the two-factor prompt is for the viewer', $GLOBALS['prompted'], array( 3 )
 ck( 'the flash on the institutions channel is drawn in the queue\'s words', has( $out, 'The application is approved.' ) && has( $out, 'wpcpm-dashboard__message--success' ), true );
 ck( 'and taken, so it shows once', isset( $GLOBALS['flash']['institutions'] ), false );
 $positions = array();
-foreach ( array( 'id="wpcpm-attention"', 'id="wpcpm-applications"', 'id="wpcpm-agreements"', 'id="wpcpm-reports"', 'id="wpcpm-requests"', 'id="wpcpm-sponsor-applications"', 'id="wpcpm-offers-low"', 'id="wpcpm-interests"', 'id="wpcpm-sponsors"', 'id="wpcpm-programs"', 'id="wpcpm-health"', 'id="wpcpm-tools"', 'wpcpm-handbook__resources' ) as $needle ) {
+foreach ( array( 'id="wpcpm-attention"', 'id="wpcpm-applications"', 'id="wpcpm-agreements"', 'id="wpcpm-reports"', 'id="wpcpm-requests"', 'id="wpcpm-sponsor-applications"', 'id="wpcpm-offers-low"', 'id="wpcpm-duplicates"', 'id="wpcpm-interests"', 'id="wpcpm-sponsors"', 'id="wpcpm-programs"', 'id="wpcpm-health"', 'id="wpcpm-tools"', 'wpcpm-handbook__resources' ) as $needle ) {
 	$positions[] = strpos( $out, $needle );
 }
 $sorted = $positions;
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-return.php` and `php bin/test-administrators-dashboard.php`. Expected:

```text
FAIL the anchors are the thirteen cards and the strip
```

```text
FAIL thirteen tiles in the spec's order, the sponsor tiles and then the duplicated students last
FAIL each tile is a number and a card
FAIL thirteen tiles now, offers running low just before the duplicated students
PHP Warning:  Undefined array key "duplicates" in bin/test-administrators-dashboard.php on line 809
PHP Warning:  Undefined array key "duplicates" in bin/test-administrators-dashboard.php on line 809
FAIL the tile reads the Student Duplicate Finder's last scan through the scan's own report
```

- [ ] **Step 3: Write the tile and the card.** Apply:

```diff
--- a/includes/modules/class-wpcpm-administrators-cards.php
+++ b/includes/modules/class-wpcpm-administrators-cards.php
@@ -149,6 +149,8 @@ final class WPCPM_Administrators_Cards {
 			// Pools under their own threshold, what sponsors said lately, and the sponsors'
 			// figures (Sponsors module, S6): read through the owning classes, never their rows.
 			'offers_low'           => self::offers_low(),
+			// The Student Duplicate Finder's last scan (1.102.0): its counts, never its rows.
+			'duplicates'           => self::duplicates(),
 			'interests'            => self::interests(),
 			'sponsors'             => self::sponsors(),
 			'programs'             => self::programs(),
@@ -157,7 +159,7 @@ final class WPCPM_Administrators_Cards {
 	}
 
 	/**
-	 * The twelve tiles of the attention strip, from the arrays the cards draw.
+	 * The thirteen tiles of the attention strip, from the arrays the cards draw.
 	 *
 	 * @param array $data What `collect()` returned.
 	 * @return array[] `label`, `n`, `card`, keyed in the strip's order.
@@ -227,6 +229,13 @@ final class WPCPM_Administrators_Cards {
 				'n'     => isset( $data['offers_low'] ) ? count( (array) $data['offers_low'] ) : 0,
 				'card'  => 'offers-low',
 			),
+			// The students the Student Duplicate Finder's last scan listed, Ready or not: the
+			// number its own screen opens with (1.102.0).
+			'duplicates'           => array(
+				'label' => __( 'Duplicated students', 'wpcredits-program-manager' ),
+				'n'     => isset( $data['duplicates']['addresses'] ) ? (int) $data['duplicates']['addresses'] : 0,
+				'card'  => 'duplicates',
+			),
 		);
 	}
 
@@ -506,6 +515,56 @@ final class WPCPM_Administrators_Cards {
 		self::card_close();
 	}
 
+	/**
+	 * Duplicated students: how many the Student Duplicate Finder lists, how many of them are
+	 * Ready, as of when, and the way to the finder, where the names are (1.102.0).
+	 *
+	 * @param array $facts duplicates()'s answer.
+	 */
+	public static function render_duplicates( array $facts ) {
+		$facts = array_merge(
+			array(
+				'addresses' => 0,
+				'ready'     => 0,
+				'read'      => 0,
+				'url'       => '',
+			),
+			$facts
+		);
+
+		self::card_open( 'duplicates', __( 'Duplicated students', 'wpcredits-program-manager' ), (int) $facts['addresses'] );
+
+		if ( empty( $facts['read'] ) ) {
+			self::empty_line( __( 'The Student Duplicate Finder has not scanned Airtable yet.', 'wpcredits-program-manager' ) );
+		} elseif ( 0 === (int) $facts['addresses'] ) {
+			/* translators: %s: date and time of the scan. */
+			self::empty_line( sprintf( __( 'No duplicated students in Airtable, as of %s.', 'wpcredits-program-manager' ), self::when( (int) $facts['read'] ) ) );
+		} else {
+			printf(
+				'<p class="wpcpm-administrator__note">%s</p>',
+				esc_html(
+					sprintf(
+						/* translators: 1: duplicated students, 2: how many of them are ready to delete, 3: date and time of the scan. */
+						_n( '%1$s duplicated student in Airtable, %2$s ready to delete, as of %3$s.', '%1$s duplicated students in Airtable, %2$s ready to delete, as of %3$s.', (int) $facts['addresses'], 'wpcredits-program-manager' ),
+						number_format_i18n( (int) $facts['addresses'] ),
+						number_format_i18n( (int) $facts['ready'] ),
+						self::when( (int) $facts['read'] )
+					)
+				)
+			);
+		}
+
+		if ( '' !== (string) $facts['url'] ) {
+			printf(
+				'<div class="wpcpm-administrator__actions"><a class="wpcpm-button wpcpm-button--secondary" href="%1$s">%2$s</a></div>',
+				esc_url( (string) $facts['url'] ),
+				esc_html__( 'Open the Student Duplicate Finder', 'wpcredits-program-manager' )
+			);
+		}
+
+		self::card_close();
+	}
+
 	/**
 	 * New interests: what sponsors said on their dashboards in the last thirty days.
 	 *
@@ -797,6 +856,25 @@ final class WPCPM_Administrators_Cards {
 		return add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $record, $page ) . '#wpcpm-sponsor-' . $card;
 	}
 
+	/**
+	 * The Student Duplicate Finder's last scan, as the tile and its card read it (1.102.0).
+	 *
+	 * Through the scan's own `report()`, as every card reads through the class that owns its data,
+	 * and the counts only: the names and addresses in the report stay on the finder's screen.
+	 *
+	 * @return array{addresses: int, ready: int, read: int, url: string}
+	 */
+	public static function duplicates() {
+		$report = class_exists( 'WPCPM_Duplicates_Scan' ) ? WPCPM_Duplicates_Scan::report() : array();
+
+		return array(
+			'addresses' => isset( $report['counts']['addresses'] ) ? (int) $report['counts']['addresses'] : 0,
+			'ready'     => isset( $report['counts']['ready'] ) ? (int) $report['counts']['ready'] : 0,
+			'read'      => isset( $report['read'] ) ? (int) $report['read'] : 0,
+			'url'       => admin_url( 'admin.php?page=wpcpm-tool-duplicate-finder' ),
+		);
+	}
+
 	/**
 	 * The tracks the Programs running card draws a tile for: track key => name, in the order
 	 * `WPCPM_Program::labels()` lists them.
```

```diff
--- a/includes/modules/class-wpcpm-administrators-dashboard.php
+++ b/includes/modules/class-wpcpm-administrators-dashboard.php
@@ -264,6 +264,7 @@ final class WPCPM_Administrators_Dashboard {
 		WPCPM_Administrators_Cards::render_sponsor_posts( isset( $data['sponsor_posts'] ) ? (array) $data['sponsor_posts'] : array() );
 		WPCPM_Administrators_Cards::render_sponsor_agreements( isset( $data['sponsor_agreements'] ) ? (array) $data['sponsor_agreements'] : array() );
 		WPCPM_Administrators_Cards::render_offers_low( isset( $data['offers_low'] ) ? (array) $data['offers_low'] : array() );
+		WPCPM_Administrators_Cards::render_duplicates( isset( $data['duplicates'] ) ? (array) $data['duplicates'] : array() );
 		WPCPM_Administrators_Cards::render_interests( isset( $data['interests'] ) ? (array) $data['interests'] : array() );
 		WPCPM_Administrators_Cards::render_sponsors_strip( isset( $data['sponsors'] ) ? (array) $data['sponsors'] : array() );
 		WPCPM_Administrators_Cards::render_programs( $data['programs'] );
```

```diff
--- a/includes/class-wpcpm-return.php
+++ b/includes/class-wpcpm-return.php
@@ -37,7 +37,7 @@ final class WPCPM_Return {
 	 * a trap for the first decision put on one of them: `field()` drops an anchor this list
 	 * does not name, silently (deep check FADMN-6).
 	 */
-	const ANCHORS = array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'interests', 'sponsors', 'programs', 'health' );
+	const ANCHORS = array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'duplicates', 'interests', 'sponsors', 'programs', 'health' );
 
 	/**
 	 * Print the hidden fields that bring a decision back to the dashboard.
```

- [ ] **Step 4: Run them and see them pass.** Expected last lines: `ALL PASS (13 checks)` and `ALL PASS (135 checks)`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.` `phpcs` on `includes/class-wpcpm-return.php` alone prints one warning, for a parameter named `$default` at line 69: it is older than this plan and one of the 85.

- [ ] **Step 6: Commit.**

```bash
git add bin/test-administrators-dashboard.php bin/test-return.php includes/class-wpcpm-return.php includes/modules/class-wpcpm-administrators-cards.php includes/modules/class-wpcpm-administrators-dashboard.php
git commit -m "Student Duplicate Finder: the Administrator Dashboard's thirteenth tile, Duplicated students, and its card"
```

---

### Task 10: The program managers' guide

Spec 12: a section in the program managers' guide, then `bin/build-docs.php`, which writes the guide's Markdown and block markup from the sections.

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (the section, before "Need help?"); `docs/administrators.md` and `docs/build/administrators.html`, as `php bin/build-docs.php` writes them
- Test: `bin/test-handbook.php`

**Interfaces:**
- Consumes: the names Tasks 6, 8 and 9 put on screen: Modules, Scan now, Ready, Needs a decision, Select all ready, Review selection, Delete, Deleted rows, View copy, the Settings card, the Duplicated students tile.
- Produces: the guide's section "Student Duplicate Finder".

- [ ] **Step 1: Write the failing check.** Apply:

```diff
--- a/bin/test-handbook.php
+++ b/bin/test-handbook.php
@@ -1070,6 +1070,7 @@ ck( 'the docs build composes a fourth guide, sponsors, from the four sponsor sec
 
 $built = file_get_contents( __DIR__ . '/../docs/build/administrators.html' );
 ck( 'the docs build escapes a section\'s text: a literal <record> reaches the HTML as text, never as a tag (1.98.1)', array( false !== strpos( $built, 'wpcpm_roster_&lt;record&gt;' ), strpos( $built, 'wpcpm_roster_<record>' ) ), array( true, false ) );
+ck( 'the program managers\' guide explains the Student Duplicate Finder, and that deleting starts switched off (1.102.0)', array( false !== strpos( $built, '>Student Duplicate Finder</h3>' ), false !== strpos( $built, 'Deleting is switched off until you turn it on' ) ), array( true, true ) );
 
 ck( 'the program managers\' guide is the handbook\'s education section', $guides['administrator']['url'], 'https://make.wordpress.org/community/handbook/education/credits/' );
 ck( 'and their channel is the program\'s', $guides['administrator']['slack'], $guides['institution']['slack'] );
```

- [ ] **Step 2: Run it and see it fail.** `php bin/test-handbook.php`. Expected:

```text
FAIL the program managers' guide explains the Student Duplicate Finder, and that deleting starts switched off (1.102.0)
```

- [ ] **Step 3: Write the section and build the guides.** Apply:

```diff
--- a/docs/sections/32-admin-tools.md
+++ b/docs/sections/32-admin-tools.md
@@ -26,6 +26,38 @@ shows the Credits Mentor's Course completion. It reads profiles, matches the bad
 it would change before it changes anything. It needs the Airtable connection, so it refuses to run
 until that is set up.
 
+### Student Duplicate Finder
+
+**WPCredits Program → Modules → Student Duplicate Finder.** Lists every student who has more than
+one row in Students, Students Reports or Feedback in Airtable, proposes which rows to delete, and
+deletes the ones you tick and confirm. A second row usually comes from the Airtable automation that
+creates a student's Students Reports and Feedback rows: it runs again for an address that already
+has them, for example when a student who did not move forward applies again.
+
+- **It scans every three hours**, after the four syncs, and **Scan now** runs a scan while you
+  watch. A scan only reads Airtable. When one fails, its error shows above the last list, which
+  stays.
+- **Ready** students have an older row in each table with nothing attached to it: one checkbox
+  selects them all, and the newest rows stay. Students who **need a decision** have a checkbox on
+  each row that may go, with the reason it was held back beside it. Nothing is ticked for you;
+  **Select all ready** ticks the Ready students.
+- **A row the site uses cannot be deleted.** When a student's account, their surveys, a mentor call
+  note or a booked call points at a row, its checkbox is disabled and says what points at it: delete
+  the other row instead.
+- **Review selection** shows exactly what will be deleted, table by table, and what was left out and
+  why. Only **Delete** on that page deletes anything. Just before it does, the finder reads the rows
+  from Airtable again and leaves out any that changed since the scan, and it never deletes the last
+  row an address has in a table.
+- **Every deleted row is kept on the site, sealed, for 30 days.** The **Deleted rows** list under
+  the students says who deleted which row and when, and **View copy** shows the row's cells so you
+  can type it back into Airtable by hand. There is no automatic restore. After 30 days the cells are
+  erased; the entry stays, without a name or an address.
+- **Deleting is switched off until you turn it on** under **WPCredits Program → Settings**, in the
+  Student Duplicate Finder's card. While it is off, the finder scans and lists and deletes nothing.
+
+On the Administrator Dashboard, the **Duplicated students** tile counts the students the last scan
+listed, and its card links here.
+
 ### Need help?
 
 The tool screen for the question box configured under Settings. Its own screen is where the handbook
```

Then run `php bin/build-docs.php`. Expected: one line per guide, then `Done.`; `git status --short` shows `docs/administrators.md` and `docs/build/administrators.html` changed beside the section, and nothing else.

- [ ] **Step 4: Run it and see it pass.** `php bin/test-handbook.php`. Expected last line: `ALL PASS`

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.` `php bin/check-spelling.php` reads the sections too.

- [ ] **Step 6: Commit.**

```bash
git add bin/test-handbook.php docs/administrators.md docs/build/administrators.html docs/sections/32-admin-tools.md
git commit -m "Student Duplicate Finder: the program managers' guide explains the finder and its switch"
```

---

### Task 11: Release 1.102.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.101.1` becomes `1.102.0` in the plugin header's `Version:` line and in `define( 'WPCPM_VERSION', ... )`:

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -3,7 +3,7 @@
  * Plugin Name:       WPCredits Program Manager
  * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
  * Description:       Runs the WPCredits program on WordPress in five modules - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a private page listing the students assigned to them, and includes the Mentor Status Checker.
- * Version:           1.101.1
+ * Version:           1.102.0
  * Requires at least: 6.5
  * Requires PHP:      7.4
  * Author:            Maciej Pilarski
@@ -19,7 +19,7 @@ if ( ! defined( 'ABSPATH' ) ) {
 	exit; // No direct access.
 }
 
-define( 'WPCPM_VERSION', '1.101.1' );
+define( 'WPCPM_VERSION', '1.102.0' );
 define( 'WPCPM_PLUGIN_FILE', __FILE__ );
 define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
 define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

- [ ] **Step 2: Move the stable tag and write the changelog entry**, first under `== Changelog ==`:

```diff
--- a/readme.txt
+++ b/readme.txt
@@ -4,7 +4,7 @@ Tags: airtable, members, roles, education, wordpress-credits
 Requires at least: 6.5
 Tested up to: 7.1
 Requires PHP: 7.4
-Stable tag: 1.101.1
+Stable tag: 1.102.0
 License: GPL-2.0-or-later
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
 
@@ -291,6 +291,12 @@ No. Uninstall removes settings, sync state, access-level meta and the custom rol
 
 == Changelog ==
 
+= 1.102.0 =
+
+* New module: the Student Duplicate Finder (WPCredits Program → Modules), from docs/specs/2026-09-11-student-duplicate-finder-design.md. It lists every student with more than one row in the Airtable tables Students, Students Reports and Feedback, proposes which rows to delete, and deletes the rows a program manager ticks and then confirms on a second page. It scans every three hours, 150 minutes into the cycle the syncs share, and on Scan now; a scan writes nothing to Airtable, and a failed one keeps the last list.
+* Nothing is deleted until a manager turns on "Deleting duplicates" under Settings: the finder ships switched off. Before a delete it reads the rows from Airtable again and asks every question again, refuses a row the site points at, a row that changed since the scan and the last row an address has in a table, keeps a sealed copy of each row on the site, and deletes children first: Feedback, then Students Reports, then Students. A daily job erases the copies after 30 days; the log of what was deleted, by whom and when, stays, without names or addresses.
+* The Administrator Dashboard gains a thirteenth tile, Duplicated students, and a small card linking to the finder. New underneath: `WPCPM_Airtable::delete_records()`, ten records a request, and `WPCPM_Request::posted_list()`.
+
 = 1.101.1 =
 
 * Invitations: nobody is sent a second set-your-password invitation within 15 minutes of the last one. Each invitation replaces the link in the one before it, and on 8 and 9 September 2026, while a class was signing in for the first time, one student was sent 17 invitations in two days and students opening an older email kept landing on "Your password reset link appears to be invalid". Resend invite on the Students and Mentors screens now refuses inside that window and says why, the invitation queue passes over anybody invited in it, and the notice after a resend says that only the newest email works.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.`, a header reading `Project-Id-Version: WPCredits Program Manager 1.102.0`, and 3138 `msgid` lines (`grep -c '^msgid ' languages/wpcredits-program-manager.pot`). WP-CLI 2.12.0 may print a `Deprecated:` line from its own `Colors.php` first; it is WP-CLI's and harmless.

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -c 'duplicate'
unzip -Z1 ../wpcredits-program-manager.zip | grep -c '^wpcredits-program-manager/\(bin\|docs\)/'
```

Expected: ` * Version:           1.102.0`, then `8` (the six classes, the stylesheet and the script), then `0`.

- [ ] **Step 6: Commit.**

```bash
git add languages/wpcredits-program-manager.pot readme.txt wpcredits-program-manager.php
git commit -m "Student Duplicate Finder: 1.102.0"
```

- [ ] **Step 7: Merge, on the product owner's choice.** If `main` has moved since Task 1, rebase `duplicate-finder` onto it and run everything again first (spec 12). Merge into `main` (fast-forward), run everything on the result, rebuild `~/GitHub/wpcredits-program-manager.zip` with `bash bin/build` from the main checkout, and remove the worktree (`git worktree remove ../duplicate-finder/wpcredits-program-manager`).

- [ ] **Step 8: Mirror.** Push the source to the public mirror, which the product owner asked for after every version bump: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps.

- [ ] **Step 9: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it over the old one as a step of its own, read the version back, and purge the edge cache. Then, read-only: the Administrator Dashboard, drawn for a Program Administrator, shows the Duplicated students tile at 0 and a card saying the finder has not scanned Airtable yet; **WPCredits Program → Modules → Student Duplicate Finder** opens with Scan now and the sentence "No scan has finished yet.", and no list; the Settings screen shows the card "Tool: Student Duplicate Finder" with deleting switched off; and `wp cron event list` shows `wpcpm_duplicates_scan` every three hours and `wpcpm_duplicates_purge` daily. The first scan then runs on its own within three hours, and only reads.

---

### Task 12: The first live check

The last item of spec 12, on the live site and the shared base. The first three steps write nothing; **each step after them waits for the product owner's yes**, asked in chat, one step at a time.

- [ ] **Step 1: The first scan.** When the scan has run on its own, or after **Scan now** on the finder's screen as a Program Administrator, the screen shows the tiles and the list, and no error above them.

- [ ] **Step 2: Compare it with the list of 10 September 2026.** The review list sent to the product owner that day (an HTML page and a CSV, kept outside the repository) named 59 addresses, 30 ready and 29 needing a decision, under rules section 5 has refined since, and the base has moved on. Account for every address that is new, gone or has changed its verdict before going on, and report the differences.

- [ ] **Step 3: Read the example in the Slack request on the screen.** It should be Ready, with its three older rows proposed, one in each table, and its newest rows kept. If it is not Ready, stop and report why before anything is deleted.

- [ ] **Step 4: On the product owner's yes, deleting is switched on** under **WPCredits Program → Settings**, in the Student Duplicate Finder's card.

- [ ] **Step 5: On the product owner's yes, delete the example.** Tick its checkbox, press Review selection, read the confirmation (3 rows: Students 1, Students Reports 1, Feedback 1), and press Delete. Expected: the notice `Deleted 3 rows: Students 1, Students Reports 1, Feedback 1. A sealed copy of each is kept until` a date 30 days on, and three entries under Deleted rows, each with View copy.

- [ ] **Step 6: Check in Airtable, read-only.** The three record IDs the log names no longer open, the student's newest rows are as they were, and the base's trash either lists the three rows or does not. That settles open item 13.1: record the answer in the spec's section 2.6 and in memory.

- [ ] **Step 7: Leave the switch as the product owner decides**, and offer a short report for the Slack thread (what was deleted, by table and record ID, and no name); post it only on the product owner's yes.

---

## What this plan leaves for later

- **The fix at the source.** A Find records step in the automation "Add students to Students Reports and Feedback" (`wflXg1xFuiCSG0pXZ`), creating the pair only when the address has none, is recommended to the program team (spec 13). Until it exists, new duplicates keep arriving and the finder keeps listing them.
- **Whether the base trash holds rows deleted through the API**: Task 12 settles it.
- **The 30-day period** is `WPCPM_Duplicate_Vault::KEEP_DAYS`, a constant until the product owner asks for a setting.
- **The Syncs and health card** lists the four syncs, not the scan: the scan's errors show on the finder's own screen. A later release may add it to the card.
- **Deliberately not in scope** (spec 14): merging rows, automatic restore, editing rows or setting a status such as Duplicated, other tables and rows with no address, and email alerts.

## What execution changed

The branch was built from this plan on 11 and 12 September 2026, one task at a time with a review after each, and a review of the whole branch at the end. Where a review found a defect in the plan's own code, the branch was fixed, so on these points the branch, not the blocks above, is the record:

- **Task 3.** `posted_list()` returns every value as a string. PHP turns an array key of decimal digits alone into an integer, so a student key of sixteen digits came back as an int; the result now passes through `array_map( 'strval', ... )`, with a check.
- **Task 8.** The Delete token is the delete action plus a hash of the sorted `table:record` pairs of the rows the confirmation lists (`rows_hash()` in place of `selection_hash()`): hashing the posted selection let a scan that finished between the two presses change what a student's key stood for (spec 7.2). `handle_delete()` checks the capability, expands the posted selection against the stored report, checks the nonce for those rows, then calls `run()`. The two buttons the script shows are hidden by `.wpcpm-duplicates__bar .button[hidden]`, because core's `.button` rule overrides the `hidden` attribute.
- **Task 10.** The guide names audit log entries among the site records that lock a row.
- **The review of the whole branch.** The Deleted rows log lists every copy that still has its cells and caps only erased entries at 50, so View copy reaches every copy for its 30 days (spec 8.3). Scan now while a scan runs says so instead of starting a second run over the first. The red Delete button disables itself on submit, the script leaves the list alone while a scan runs, a locked row's disabled checkbox names its row, and checks pin a copy that cannot be kept and the confirmation during a scan.
