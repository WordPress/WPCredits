# Plugin 1.123.55: test institutions, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An institution whose name starts with the word "TEST" in capitals is a test institution: it, and everything of its (its application, agreement, requests, semester reports, representatives and students), is listed wherever a real one is, with a "Test" mark beside its name, and left out of every number the site draws, mails or answers; accepting its Collaboration Agreement never moves its Airtable stage to Confirmed; and everything else it does works as for any institution.

**Architecture:** One new class, `WPCPM_Test_Institutions` (`includes/modules/class-wpcpm-test-institutions.php`), decides it for a record ID or a name through `WPCPM_Sponsors_Index::is_test_name()`, keeps the remembered list the institutions sync writes beside the index, and gives every count site a filter (for maps keyed by record ID, for rows holding a record ID or a name, for post IDs, for applications and for accounts) and every list the mark. The lists keep every item; each count site counts through a filter. Nothing new is stored on an institution, a post or an account.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); the no-WordPress suites `bin/test-*.php` (run from the plugin root, each ends `ALL PASS` and exits 0); the Docker copy in `~/Projects/wpcredits-docker` (`bin/wp`, `bin/snapshot`, `bin/restore`, `bin/check`); the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** `docs/specs/2026-11-05-test-institutions-design.md` (approved 5 November 2026, committed on main as ee023883). It is the binding authority: read all of it before any task. Sections 3 to 7 are what this plan builds; section 5's rule ("a number that counts institutions, or anything of theirs, leaves a test institution out") decides any count the appendix and this plan missed; section 9's anti-goals are what the tests guard.

## Rulings

Where the design leaves a detail open, or the code does not match the design's words, the plan rules here, once; each task that builds a ruling names it.

1. **Base and number.** 1.123.55 is built on a branch made from main once main holds 1.123.54 (student registration, building on `feature/student-registration`). Before Task 1 checks main reads 1.123.54 in the plugin header, `WPCPM_VERSION` and the Stable tag; if it does not, stop and tell the product owner. If main has moved past 1.123.54, take the next free number wherever this plan says 1.123.55. Branch `feature/test-institutions`, worktree `.worktrees/test-institutions`. Commit messages start `Test institutions:`.
2. **The helper is `WPCPM_Test_Institutions::is_test( $institution )`**, in a class of its own beside the index, not a method of `WPCPM_Institutions_Index`: fourteen suites and two shared stubs stand the index in at its contract (`row()`, `rows()`), so a class that reads the index only through `row()` and `rows()` loads in any of them by its file, and the index keeps its one job. A value `WPCPM_Mentors_Sync::is_record_id()` accepts is a record ID: while the index holds it, its `name` there decides (`WPCPM_Sponsors_Index::is_test_name()`); while it does not, it is a test exactly when the remembered list holds it. Any other string is a name and `is_test_name()` decides. A value that is not a string is never a test.
3. **The remembered list** is the option `wpcpm_test_institutions` (`WPCPM_Test_Institutions::OPTION`), the sorted record IDs found to be tests, not autoloaded. It is written in one place: `WPCPM_Institutions_Sync::phase_records()`, right before its `WPCPM_Institutions_Index::write()` (line 804), from the index about to be replaced (`$held`, read at line 791) and the rows about to be written (`$state['rows']`): for each record ID in either, the name it is about to be written with, or else the name it was held with, decides whether it is on the list; an ID in neither keeps its place. Written only when it changes. Why there: it is the only place an institution leaves the index, so a test row removed from Airtable is remembered at the very write that drops it, including one the approval inserted or the details patched since the last sync; it runs in the sync's tick (cron or an Administrator's press), so no public read path, ability or dashboard draw writes an option; and a renamed row leaves or joins the list at the same write that renames it in the index. `WPCPM_Institutions::uninstall()` deletes it with the index.
4. **A name in the index always wins.** `is_test()` reads the index first and the list only for an ID the index does not hold, so a row renamed away from "TEST" is real from the sync that brings the new name, before the list is next written.
5. **The student registration form switches to the helper.** `WPCPM_Student_Registration_Form::institutions()` (1.123.54) leaves out a row for which `WPCPM_Test_Institutions::is_test( $record_id )` holds, in place of `WPCPM_Sponsors_Index::is_test_name()` on the row's name. The answers are the same for every row the index holds (the 1.123.54 build's ruling: "test college" and "Testing Academy" offered), and one helper decides it everywhere (design, section 3).
6. **The mark.** In wp-admin: ` <span class="wpcpm-inst-mark wpcpm-inst-mark--test" title="A test institution, whose name starts with TEST: listed here and left out of every count.">Test</span>`, the pipeline's own small flag in `assets/css/admin.css` (`.wpcpm-inst-mark`, which the held, duplicate and half-done flags reuse); `--test` is a hook with no rule of its own, as `--empty` and `--space` are. On the Administrator Dashboard, which loads `administrator.css` and, through its dependency, `dashboard.css` but never `admin.css`: the same span with `class="wpcpm-badge wpcpm-badge--test"`, the dashboards' small badge (`.wpcpm-badge` in `assets/css/dashboard.css`). The word is `_x( 'Test', 'the mark beside a test institution', 'wpcredits-program-manager' )`, apart from the email log's "Test" filter. Where no markup can go (an `<option>`, a combobox entry, a mail line, a sentence listing names) the name reads `<name> (Test)` (`label()`). No CSS changes.
7. **Where the mark shows:** every list of section 4, as the table in Task 3 names them site by site, and the Students screen's Institution column. **The test institution's own Institution Dashboard says nothing more**: it is drawn as for any institution, since its representatives are the testers and test what a real institution sees; an Administrator reaches it through a Viewing as entry that reads "(Test)".
8. **A count over a list counts the real items; the list draws every item, in its place.** That includes a card's number beside its title, a folded list's summary, "N more are waiting", "Showing the oldest N of M" and "the N most recently edited approved reports": N and M count real items. A list that holds only test items still draws them, its number reading 0.
9. **What makes an item a test institution's:** an application by its name (the post title, the name as given on the form); an agreement, a request and a semester report by the record ID its meta carries (`WPCPM_Institution_Agreement::META_INSTITUTION`, `WPCPM_Institution_Request::META_INSTITUTION`, `WPCPM_Semester_Report::META_INSTITUTION`, or the `record` and `institution` keys of the rows built from them); an institution account by `WPCPM_Institution_Members::institution_of()`; a student account by its stamp `WPCPM_Students_Sync::META_INSTITUTION`, or, with no stamp, its program row's institution name; a roster row by the roster it is on.
10. **The menu bubble's bounded read.** `queue_count()` keeps asking each reader for `COUNT_MAX + 1` and drops the test items from what came back, so below the ceiling the bubble, the queue's heading and the dashboard's tiles are one number; at the ceiling it may read a little under "200+" while test items are among the oldest 201, which no real site reaches.
11. **The applications' titles are read in one query that primes no meta** (`get_posts()` over the IDs with `update_post_meta_cache` and `update_post_term_cache` false): the totals of the Administrator Dashboard must not load every applicant's answers (deep check FADMN-2).
12. **The semester reports' due list keeps a test institution's pairs**, so the daily job still drafts its report and mails the Administrators (design, section 7); every count of the list leaves them out.
13. **The Programs card leaves a test institution out entirely**: its per-institution rows are figures (students in progress, waiting, mentors), so the row goes with the totals, the "N institutions" phrase and `wpcpm-program-counts`'s `institutions_with_students`. The test institution's own roster is on its Institution Dashboard.
14. **The Invitations cards' buttons count every account they invite**, a test institution's included: the number says what the press does, and the press still invites them (design, section 7). They are the one exception to section 5, and the guide says so.
15. **The agreement reminder digest** goes out as today whenever anything is overdue, a test agreement included, so its testers receive it; its two counts count the real overdue agreements; its list names every overdue one, a test one as `<name> (Test), uploaded <day>`. With only a test agreement overdue, it reads "0 signed agreements are waiting for review".
16. **The Sync and storage tab.** The Reconciliation card's numbers leave a test institution's Students rows out, counted so in `WPCPM_Students_Sync::reconciliation()` when the students sync runs (the card reads what the sync kept, tallied by status, with no institution left to filter by); its row lists (the status disagreements, the missed joins) still list them. "Contacts who are not members" leaves a test institution out. The Airtable sync card's progress (what a running sync has read) is the sync's own work and stays as it is.
17. **The abilities.** `wpcpm-institution-summary`'s match carries `test` (boolean), right after `name`, in the schema as a required key: "True for a test institution, whose name starts with the word TEST: it is left out of every count the site gives." `wpcpm-program-counts` and `wpcpm-waiting-for-decision` leave it out through the readers they share with the dashboard. `wpcpm-find-student` and `wpcpm-mentor-students` are lookups that list tracks and total nothing (design vs code: section 5 speaks of "the student counts the mentor and student abilities total", but each answers a list and `more`), so they list a test institution's tracks as they list any, unchanged.
18. **The Students screen.** Its "Students by status" summary, its status picker's counts and its institution picker's counts never count a test institution's students, whatever is chosen; the test institution's option reads `<name> (Test)` in place of its count; chosen, its list still lists every one of its students. The All, Invited and Never invited views of every accounts list leave out the accounts of a test institution (`uncounted_ids()`).
19. **The stage write asks the helper by the record ID** and does not also read the name in the live record it has just fetched: one rule, read one way (design, section 3). For a test institution the acceptance writes its five agreement cells and no `Current Stage`; for a real one it still writes Confirmed when the stage precedes it. Approval keeps writing the new row at `institution_new_stage`.
20. **The shared seed has a test institution now.** `bin/fixtures/institutions-index-seed.json` holds one TEST record (`recSEED0000000001`, "TEST - Institution 20", Under Review), anonymized from the live base's own. It stays: the suites seeded from it see it left out, and each pinned number that held it moves by one, its label saying "the TEST record left out". The suites whose one institution is named "TEST - Institution 20 " (invite, members, people, request, unlinked link) keep the name; where a pinned output of theirs gains the mark or the label, the assertion takes it.
21. **Stand-ins.** A suite that stands in `WPCPM_Sponsors_Index` gains `is_test_name()` copied from the real one, body unchanged; a suite with no stand-in requires `includes/modules/class-wpcpm-sponsors-index.php`; every suite that reaches the helper requires `includes/modules/class-wpcpm-test-institutions.php`. `bin/test-test-institutions.php` holds every copied body to the real one.

## Global Constraints

- Release **1.123.55**: the plugin header `Version`, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.123.55 =` changelog entry above 1.123.54's, the translation template with `sh bin/make-pot.sh`, and the guides with `php bin/build-docs.php`. Base: main holding 1.123.54 (Ruling 1).
- PHP 7.4 compatible, suites included: `array()` syntax, no `match`, no named arguments, no typed properties, no `?->`, no trailing comma in a parameter list or a closure's `use` list, order with `array_multisort()` and an ID tie-break rather than relying on `usort()` keeping equal rows in place; a suite that reaches a private or protected member through reflection calls `setAccessible( true )` first, under `if ( PHP_VERSION_ID < 80100 )` (`bin/test-tooling.php` reads for it).
- WordPress coding standards: tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`, no short ternary. Run `export PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH"` first (its `phpcs` runs with 1G of memory), then `bash bin/check-standards.sh`: it ends with the warning count Before Task 1 records and no ` ERROR ` line.
- Every handler checks the capability first, then the nonce keyed to the action and the row; every printed value is escaped (a function that returns markup, such as `mark()`, escapes inside, and its callers say so in their `phpcs:ignore` comment); every string with a placeholder has a translator comment.
- **No em dash and no en dash anywhere**: plan, code, comments, strings, docs, tests, commit messages. Plain hyphens.
- **US English** in every string a person reads (`php bin/check-spelling.php`); full product names (Student Report Card, Mentor Report Card); never "Education Hub"; "Administrators" for the people who run the program, in every string the plugin draws.
- **No person's name** in the plan, code, comments, fixtures, docs, tests or commit messages: name roles (the product owner, a program manager, an Administrator, a tester). No personal email address anywhere: tests and the trial use `example.test`. Invented Airtable IDs are the type's prefix and upper case letters and digits only (`recTESTINSTITUT01`, `recINSTITUTION001`); `php bin/test-fixtures.php` walks `bin/`, `docs/` and `includes/` for them.
- **Tests first in every task**, in the repository's style (`ck( $label, $got, $want )`, compared with `===`), each shown failing before its fix. Each count test builds the same world with and without a test institution and asserts the same number, and asserts the test item is still listed. **Airtable is always faked.**
- **What the site writes to Airtable** in this release: exactly what it writes today, less the `Current Stage` cell of a test institution's agreement acceptance. Nothing new is written, updated or deleted there.
- **Everything else works.** No email, drafting, generation, upload, acceptance, return, revoke, enrollment, invitation or sync behaves differently for a test institution, and no list leaves it out: only numbers change, and the one stage cell (design, sections 6 and 7).
- **Hands off**: Tasks 1 to 9 touch no network, no SSH, no Docker and no Airtable; Task 10's check reads the public repository and the live version as `release.py` does, and writes nothing; Task 11 alone touches the Docker copy, inside a snapshot; Task 12's SSH, mirror and deploy steps each wait for the product owner's explicit yes. No stash, no push but the release script's.
- Commits: each message starts `Test institutions:` and ends, after a blank line, with the Co-Authored-By trailer the implementer's session names for its model.

## Review Focus

1. **A real institution whose name only looks like a test**: "Test College", "Contest Labs", "TESTAMENT Academy", "TEST2 Labs" and "test college" are real (counted, unmarked); " TEST - Leading Space College" (a leading space), "TEST", "TEST: Example" and "TEST_Example" are tests. Pinned in Task 1, step 1, and Task 5, step 1 (a "Test College" row counted on the Pipeline, unmarked).
2. **An institution renamed in Airtable between two syncs**: renamed away from "TEST", it is counted and unmarked from the sync that brings the new name and leaves the list at that sync's write; renamed to "TEST", it is left out from that sync on and joins the list. Pinned in Task 1, step 1 (the helper and two runs of the sync).
3. **The test institution's row removed from Airtable after testing** while its agreement, requests and semester report stay on the site: the next sync drops it from the index and remembers it; the bubble, the queue's heading and the tiles still leave its items out, and the queue and the cards still list them, named by record ID, with the mark. Pinned in Task 1, step 1, and Task 4, step 1.
4. **The menu bubble, the queue and the dashboard's tiles agree on one number**: the same world (three open applications, one agreement waiting for review, two open requests, plus one test item of each kind, the test agreement and request overdue) gives 6 on the bubble, 6 on the Waiting for review heading, and tiles of 3, 1 (0 overdue) and 2, with and without the test items. Pinned in Task 4, step 1, in both suites.
5. **A list a tester needs still shows the test item, with its mark**: every count test of Tasks 4 to 7 also asserts the test item listed; Task 3 asserts the mark in each list. Pinned in Task 3, step 1, and in each count task's step 1.

---

## Files at a glance

| File | What changes | Task |
| --- | --- | --- |
| `includes/modules/class-wpcpm-test-institutions.php` (new) | The rule, the remembered list, the filters, the mark | 1 |
| `includes/modules/class-wpcpm-institutions-sync.php` | `phase_records()`: the list written before the index (lines 787-804) | 1 |
| `wpcredits-program-manager.php`, `uninstall.php` | `require_once` of the new class right after `class-wpcpm-institutions-index.php`'s (lines 110 and 122 on ee023883) | 1 |
| `includes/modules/class-wpcpm-institutions.php` | `uninstall()` (611); the marks (Task 3); the queue and bubble (Task 4); the index totals (Task 5); the locked accounts' notice (Task 6) | 1, 3, 4, 5, 6 |
| `includes/modules/class-wpcpm-student-registration-form.php` (1.123.54) | `institutions()` on the helper | 1 |
| `includes/modules/class-wpcpm-institution-agreement.php` | `handle_accept()`'s stage cell (1477-1480); `remind()` (2331) | 2, 4 |
| `includes/modules/class-wpcpm-administrators-cards.php` | the marks (Task 3); `collect()`, `counts()`, the cards' numbers (Task 4); `programs()` (Task 6) | 3, 4, 6 |
| `includes/modules/class-wpcpm-institution-application.php` | `pending_ids()` beside `pending_count()` (2600) | 4 |
| `includes/modules/class-wpcpm-institutions-table.php`, `includes/modules/class-wpcpm-students-table.php`, `includes/class-wpcpm-accounts-table.php` | the Institution columns' marks (Task 3); the views' and pickers' counts (Task 6) | 3, 6 |
| `includes/modules/class-wpcpm-semester-report-screen.php`, `includes/modules/class-wpcpm-institution-roster.php` | picker labels | 3 |
| `includes/modules/class-wpcpm-students-sync.php` | `reconciliation()` | 6 |
| `includes/class-wpcpm-ability-answers.php`, `includes/modules/class-wpcpm-sponsor-report-numbers.php`, `bin/stubs/ability-readers.php` | the abilities; the sponsor report's numbers | 7 |
| `docs/sections/30-admin-wpadmin.md`, `docs/sections/34-admin-operations.md` | The guide | 8 |
| `bin/test-test-institutions.php` (new) | The helper's suite | 1 |

## The checks every task ends with

Run from the plugin root of the worktree, after the task's own suites pass:

```bash
export PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH"
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
php bin/check-temp-litter.php | tail -2
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: `battery done` with no `FAIL` line; references `- all resolve`; spelling `US English throughout.`; `0 dead`; temp litter as found; the standards line Before Task 1 records. A suite outside the task's list that fails only because its world now holds a test institution is fixed in that suite by Ruling 20 or 21, never by changing the code back, and the task's report names it. A task that changes `docs/sections/` runs `php bin/build-docs.php` before the battery.

---

## Before Task 1: the base, the branch and the plan

- [ ] **Step 1: The base.** In `~/GitHub/wpcredits-program-manager`: `git log --oneline -5` shows the merge of `feature/student-registration`; `grep -n "^ \* Version:\|define( 'WPCPM_VERSION'" wpcredits-program-manager.php` and `grep -n "^Stable tag:" readme.txt` read 1.123.54; `grep -n "is_test_name" includes/modules/class-wpcpm-student-registration-form.php` finds the call in `institutions()`. Anything else: stop and tell the product owner (Ruling 1).
- [ ] **Step 2: The branch.** `git worktree add .worktrees/test-institutions -b feature/test-institutions main` (superpowers:using-git-worktrees). In it, run the checks above and `php bin/test-fixtures.php`; write their last lines and the suite count in the session's ledger. The line numbers in this plan are main's at ee023883. Of the files this plan edits, 1.123.54 changes the two loader files (its `require_once` lines sit above the index's, so line 110 and 122 move down) and `docs/sections/30-admin-wpadmin.md` and `34-admin-operations.md`; each task reads its own lines again before editing.
- [ ] **Step 3: The plan names nobody and holds no refused word.**

```bash
grep -n -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/plans/2026-11-05-plugin-1-123-55-test-institutions.md || echo clean
php -r 'require "bin/refused-strings.php"; var_dump( wpcpm_refused_found( file_get_contents( "docs/plans/2026-11-05-plugin-1-123-55-test-institutions.md" ) ) );'
python3 -c 'import re,sys,tomllib; w=tomllib.load(open(sys.argv[1],"rb"))["scan"]["private_words"]; t=open(sys.argv[2],encoding="utf-8").read(); print(sum(len(re.findall(p,t,re.I)) for p in w))' ~/Projects/wpcredits-release/config.toml docs/plans/2026-11-05-plugin-1-123-55-test-institutions.md
```

Expected: `clean`, `array(0) {}`, `0`. The third prints a count, never a word of the list.

---

### Task 1: The rule, the remembered list and the one helper

**Files:**
- Create: `includes/modules/class-wpcpm-test-institutions.php`, `bin/test-test-institutions.php`
- Modify: `includes/modules/class-wpcpm-institutions-sync.php` (`phase_records()`, lines 787-804)
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (the `require_once` right after `class-wpcpm-institutions-index.php`'s), `includes/modules/class-wpcpm-institutions.php` (`uninstall()`: `delete_option( WPCPM_Test_Institutions::OPTION );` after the index's two)
- Modify: `includes/modules/class-wpcpm-student-registration-form.php` (`institutions()`, Ruling 5)
- Test: `bin/test-test-institutions.php`, `bin/test-institutions-sync.php`, `bin/test-uninstall.php`, `bin/test-student-registration.php`; run `bin/test-institutions-index.php`, `bin/test-sponsors-store.php`

**Interfaces:**
- Consumes: `WPCPM_Sponsors_Index::is_test_name( $name )`; `WPCPM_Institutions_Index::row()`, `rows()`; `WPCPM_Mentors_Sync::is_record_id()`; `WPCPM_Institution_Members::institution_of( $user )`.
- Produces `final class WPCPM_Test_Institutions`:
  - `OPTION = 'wpcpm_test_institutions'`; `MARK_ADMIN = 'wpcpm-inst-mark wpcpm-inst-mark--test'`; `MARK_DASHBOARD = 'wpcpm-badge wpcpm-badge--test'`.
  - `is_test( $institution ): bool` (Rulings 2 and 4), trimming a record ID as `row()` does.
  - `remembered(): string[]`: the option's values that are record IDs, trimmed, once each, sorted; `array()` for an absent or malformed option.
  - `test_ids(): string[]`: the record IDs that are tests now, sorted: the index's rows whose name is a test name, and the remembered IDs the index does not hold.
  - `remember( array $before, array $after ): string[]`: Ruling 3, both arrays keyed by record ID (index rows, or rows in the index's shape); `update_option( OPTION, $list, false )` only when `$list !== remembered()`; returns the list.
  - `real_keys( array $by_record ): array`: a map keyed by institution record ID, less the keys that are tests, keys and order kept.
  - `count_real_rows( array $rows, $field ): int`: how many of the rows are not a test institution's, each row an array (`$row[ $field ]`) or an object (`$row->$field`, such as a `WP_Post`'s `post_title`) holding a record ID or a name; a row without the field is real.
  - `real_post_ids( array $post_ids, $meta_key ): int[]`: the posts whose `$meta_key` meta (a record ID) is not a test institution's, in their order, the meta primed first with `update_meta_cache( 'post', $ids )` where that function exists.
  - `real_application_ids( array $post_ids ): int[]`: the applications whose title is not a test name, in their order, the titles read in one query that primes no meta (Ruling 11).
  - `count_real_accounts( array $users ): int`: how many institution accounts do not act for a test institution.
  - `mark( $institution, $classes = self::MARK_ADMIN ): string`: Ruling 6's span with its leading space, escaped, or `''` for a real one.
  - `label( $name, $institution ): string`: `sprintf( /* translators: %s: an institution's name. */ __( '%s (Test)', 'wpcredits-program-manager' ), $name )` for a test, `$name` as given otherwise.

- [ ] **Step 1: Write the failing tests.**
  - `bin/test-test-institutions.php`, a new suite in `bin/test-institutions-index.php`'s frame (`$GLOBALS['opts']` with `get_option()`, `update_option()` keeping `$GLOBALS['autoload']` and counting `$GLOBALS['writes']`, the `ck()` and the `ALL PASS` ending), loading the real `class-wpcpm-sponsors-index.php`, `class-wpcpm-institutions-index.php` and the new class, with a `WPCPM_Mentors_Sync::is_record_id()` stand-in of the real pattern and a `WPCPM_Institution_Members::institution_of()` stand-in reading `$GLOBALS['acts_for']`. `index_world( array $names )` writes the index from record ID to name. Sections:
    - "The rule" (**Review Focus 1**):

```php
$names = array( 'TEST - Example Institution', ' TEST - Leading Space College', 'TEST', 'TEST: Example', 'TEST_Example', 'Test College', 'Contest Labs', 'TESTAMENT Academy', 'TEST2 Labs', 'test college', '' );
ck( 'a name: the word TEST in capitals, trimmed, then nothing or no letter or digit', array_map( array( 'WPCPM_Test_Institutions', 'is_test' ), $names ), array( true, true, true, true, true, false, false, false, false, false, false ) );
ck( 'which is the sponsors\' rule, name for name', array_map( array( 'WPCPM_Test_Institutions', 'is_test' ), $names ), array_map( array( 'WPCPM_Sponsors_Index', 'is_test_name' ), $names ) );
ck( 'nothing that is not a string is a test', array( WPCPM_Test_Institutions::is_test( null ), WPCPM_Test_Institutions::is_test( array( 'TEST' ) ), WPCPM_Test_Institutions::is_test( 12 ) ), array( false, false, false ) );
```

    - "A record ID": `index_world()` of `recTESTINSTITUT01` "TEST - Example Institution", `recINSTITUTION001` "Example University", `recINSTITUTION003` "Test College": `is_test()` of each is `true`, `false`, `false`; `" recTESTINSTITUT01\n"` is `true`; `recINSTITUTION099` (held nowhere) is `false`; `test_ids()` is `array( 'recTESTINSTITUT01' )`.
    - "Remembered" (**Review Focus 3**): `remember( <the three>, <the two real ones> )` answers `array( 'recTESTINSTITUT01' )`, stores it with autoload `false`; the index then written without it: `row()` null, `is_test( 'recTESTINSTITUT01' )` true, `test_ids()` still `array( 'recTESTINSTITUT01' )`; the same `remember()` again writes nothing (`$GLOBALS['writes']` unchanged); a world with no test institution: `remember()` writes no option at all.
    - "Renamed" (**Review Focus 2**): with the list holding `recTESTINSTITUT01`, `remember( $before, $after )` whose `$after` names it "Example Institute" answers `array()`; one whose `$after` names `recINSTITUTION001` "TEST - Example University" adds it. With the list holding `recINSTITUTION001` (planted) and the index naming it "Example University", `is_test( 'recINSTITUTION001' )` is false and `test_ids()` lacks it (Ruling 4).
    - "A malformed list": the option a string, or `array( 'not a record', 7, 'recTESTINSTITUT01', 'recTESTINSTITUT01' )`: `remembered()` is `array()` and `array( 'recTESTINSTITUT01' )`.
    - "The filters": `real_keys()` of `array( 'recINSTITUTION001' => 1, 'recTESTINSTITUT01' => 2, 'recINSTITUTION003' => 3 )` is the first and third, in order; `count_real_rows()` of three arrays whose `record` is the test ID, a real ID and `''`, by `record`, is 2; of three `WP_Post` stand-ins titled "TEST - Example Application", "Example University" and "Test College", by `post_title`, is 2; `real_post_ids( array( 11, 12 ), '_wpcpm_agr_institution' )` over meta naming the test and a real ID is `array( 12 )`; `real_application_ids()` of the same three titled posts is the last two IDs; `count_real_accounts()` of two accounts acting for the test and a real ID is 1.
    - "The mark": `mark( 'recTESTINSTITUT01' )` is exactly ` <span class="wpcpm-inst-mark wpcpm-inst-mark--test" title="A test institution, whose name starts with TEST: listed here and left out of every count.">Test</span>`; with `MARK_DASHBOARD` the class is `wpcpm-badge wpcpm-badge--test`; `mark( 'recINSTITUTION001' )` is `''`; `label( 'TEST - Example Institution', 'recTESTINSTITUT01' )` is `TEST - Example Institution (Test)`, `label( 'Example University', 'recINSTITUTION001' )` is `Example University`; `assets/css/admin.css` holds `.wpcpm-inst-mark {` and `assets/css/dashboard.css` holds `.wpcpm-badge {` (Ruling 6).
    - "The stand-ins" (Ruling 21): every `bin/*.php` and `bin/stubs/*.php` that defines `function is_test_name(` holds the real method's body, read from `includes/modules/class-wpcpm-sponsors-index.php` with `ReflectionMethod::getStartLine()` and `getEndLine()`, whitespace aside.
  - `bin/test-institutions-sync.php`, a section "Test institutions are remembered where the index is written" (the suite requires the new class and the real sponsors index): `reset_site( $seed ); run_to_end();` leaves `WPCPM_Test_Institutions::remembered()` `array( 'recSEED0000000001' )` with autoload `false` (the seed's TEST record); a second run whose `airtable_pages( $seed, array( 'recSEED0000000001' => array( 'Name' => 'Institution 20' ), 'recSEED0000000008' => array( 'Name' => 'TEST - Institution 4' ) ) )` replaced `$GLOBALS['pages']` leaves it `array( 'recSEED0000000008' )` (**Review Focus 2**); a third whose pages leave the record `recSEED0000000008` out (filtered from `airtable_pages()`) leaves the index without it, the list with it, and `is_test( 'recSEED0000000008' )` true (**Review Focus 3**); a run the no-fields guard refuses (every record without `Name` and `Current Stage`) changes neither the index nor the list.
  - `bin/test-uninstall.php`: `$GLOBALS['opts']['wpcpm_test_institutions'] = array( 'recSEED0000000001' );` is gone after the uninstall; the constants map gains `'WPCPM_Test_Institutions::OPTION' => 'wpcpm_test_institutions'`.
  - `bin/test-student-registration.php` (1.123.54's suite, which requires the new class): the index gains `recINSTITUTION008` "TEST: Example Campus" and `recINSTITUTION009` "TESTAMENT College", both Confirmed: `institutions()` offers the second and not the first; its existing assertions ("TEST - Example institution" not offered, "test college" and "Testing Academy" offered) pass unchanged; `institutions()`'s source holds `WPCPM_Test_Institutions::is_test(` and no `is_test_name(`.
- [ ] **Step 2: Run them red.** `php bin/test-test-institutions.php; php bin/test-institutions-sync.php; php bin/test-uninstall.php; php bin/test-student-registration.php`: the class is missing, the list is never written, the option survives, the form calls `is_test_name()`.
- [ ] **Step 3: Implement** as the interfaces say. In `phase_records()`, `WPCPM_Test_Institutions::remember( $held, $state['rows'] );` right before `WPCPM_Institutions_Index::write( $state['rows'], $state['started'] );`, after the no-fields guard. The class docblock gives the rule, the list and why it is written there (Ruling 3) in the plugin's voice; `write()`'s docblock in the index gains one line: the sync remembers its test institutions just before this write.
- [ ] **Step 4: Run green, then the checks; commit.** `git commit -m "Test institutions: the rule, the remembered list written beside the index, and the student form on the one helper"`.

### Task 2: The acceptance leaves a test institution's stage alone

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`handle_accept()`, the stage cell at lines 1477-1480)
- Test: `bin/test-institution-agreement.php` (which requires the new class and the real sponsors index)

**Interfaces:**
- Consumes: `WPCPM_Test_Institutions::is_test( $record )` (Task 1).

- [ ] **Step 1: Write the failing tests**, a section "handle_accept(): a test institution keeps its stage" after the existing acceptance checks:
  - `$id = review_world();` then `seed_index( 'recAAAAAAAAAAAAA1', 'TEST - Universidad Example' );` then `run( 'handle_accept' )`:

```php
ck( 'a test institution\'s acceptance carries the five agreement cells and no stage', patched_cells(), array(
	'Agreement Status'           => 'Accepted',
	'Agreement Kind'             => 'Program template',
	'Agreement Accepted On'      => gmdate( 'Y-m-d' ),
	'Agreement Accepted By'      => $GLOBALS['users'][1]->display_name,
	'Agreement Template Version' => '2025-11-04',
) );
ck( 'in one PATCH, and the stage it read is the stage it leaves', array( count( $GLOBALS['patched'] ), $GLOBALS['audit'][0]['data']['stage'], $GLOBALS['audit'][0]['data']['confirms'] ), array( 1, 'Under Review', 0 ) );
```

  - and everything else as for a real one (design, section 7): the outcome `agreement-accepted`, `is_settled( 'recAAAAAAAAAAAAA1' )` true, `mail_log()` the same two `agreement-accepted` lines as the real acceptance above.
  - `review_world( 'template', '' )` with the test name: still no `Current Stage` (an empty stage precedes Confirmed, and the test rule wins over it).
  - The real acceptance above still writes `'Current Stage' => 'Confirmed'` (its existing check stays as it is).
  - No other code writes a stage (design, section 6): across every file under `includes/`, one assignment to `$cells[ $fields['stage'] ]`, in `class-wpcpm-institution-agreement.php`, the line before it holding `WPCPM_Test_Institutions::is_test( $record )`; and one `$fields['stage'] =>`, the approval's new row in `class-wpcpm-institution-approval.php`, at `institution_new_stage` (left as it is: design, section 6).
- [ ] **Step 2: Run them red.** `php bin/test-institution-agreement.php`: the test institution's PATCH holds `Current Stage`.
- [ ] **Step 3: Implement.** `if ( self::precedes_confirmed( $stage ) && ! WPCPM_Test_Institutions::is_test( $record ) ) {`; the comment above it gains one sentence: a test institution keeps its stage, so the public pages that list Confirmed institutions never show it (design, section 6).
- [ ] **Step 4: Run green, then the checks; commit.** `"Test institutions: accepting a test institution's agreement leaves its Airtable stage as it was"`.

### Task 3: The Test mark in every list

**Files:**
- Modify: the functions the table below names
- Test: the suites the table names (each requires the new class and an `is_test_name()` source, Ruling 21)

**Interfaces:**
- Consumes: `WPCPM_Test_Institutions::mark()`, `label()`, `MARK_DASHBOARD` (Task 1).

Every mark is drawn right after the name, inside the same cell, heading or item; every label replaces the name where only text can go. `R` is the item's record ID, `N` its name.

| List | Function (file) | Draws | Suite |
| --- | --- | --- | --- |
| Waiting for review, a row | `render_queue_row()` (`class-wpcpm-institutions.php`, 3198) | `mark( R or, for an application, N )` after the name span | `bin/test-institutions-screen.php` |
| Waiting for review, the open application | `render_application()` (3359), its `<h2>` | `mark( N )` | same |
| Pipeline, a row | `render_pipeline_row()` (4471), beside the `--space` flag | `mark( R )` | same |
| Pipeline, Find an institution | `render_pipeline_find()` (4372) | `label( N, R )`, then `, City (Stage)` | same |
| Accounts, locked accounts | `render_locked_accounts()` (2963) | `label( "Name (login)", institution_of( user ) )` | same |
| Accounts, Manage members | `render_members()` (2816), its `<h2>` | `mark( R )` | same |
| Semester reports, a report; Due for drafting, a pair | `render_semester_report_row()` (5979); `render_report_due()` (6024) | `mark( R )` | same |
| Agreements on file; in force; discrepancies; template versions | `render_agreements_on_file()` (4700); `render_agreements_in_force()` (4780); `render_discrepancies()` (5803); `render_template_versions()` (6202) | `mark( R )`; `label( N, R )` in the versions' name lists | same |
| Sync and storage, duplicate emails; the Link form | `render_reconciliation()` (5214); `link_choices()` (5705) | `label( N, R )` | same; `bin/test-unlinked-link.php` |
| Institution accounts, the Institution column | `WPCPM_Institutions_Table::column_institution()` (567), for an account and for a No account row | `mark( R )` | `bin/test-institutions-accounts.php` |
| Student accounts, the Institution column | `WPCPM_Students_Table::column_institution()` (795) | `mark()` of the student's institution (Ruling 9) | `bin/test-students-screen.php` |
| Draft any semester | `WPCPM_Semester_Report_Screen::render_draft_picker()` (677) | `label( N, R )` | `bin/test-semester-report.php` |
| Viewing as, on the Institution Dashboard | `WPCPM_Institution_Roster::switcher_options()` (247) | `label( N, R )` | `bin/test-institution-roster.php` |
| Administrator Dashboard: agreements, requests, the details request, reports to review, due and approved | `WPCPM_Administrators_Cards::institution_link()` (3599), after the link | `mark( R, MARK_DASHBOARD )` | `bin/test-administrators-dashboard.php` |
| Administrator Dashboard: an application | `render_application()` (2220), in its `<h4>` after the link | `mark( N, MARK_DASHBOARD )` | same |
| Administrator Dashboard: locked accounts | `render_health()` (2940), the names | `label( "Name (login)", institution_of( user ) )` | same |

- [ ] **Step 1: Write the failing tests.** For each row of the table, in its suite, the list with one test item and one real item: the test item's markup holds the mark (`wpcpm-inst-mark--test` in wp-admin, `wpcpm-badge--test` on the Administrator Dashboard) exactly once, right after its name, or its text reads `<name> (Test)`; the real item holds neither; the list's items and their order are those it drew before. The test item is `recTESTINSTITUT01` "TEST - Example Institution" where the suite builds its own world, and the seed's `recSEED0000000001` "TEST - Institution 20" in `bin/test-institutions-screen.php`, whose Pipeline row now carries `wpcpm-inst-mark--test` once and whose Find entry reads `TEST - Institution 20 (Test), City 19 (Under Review)`. In `bin/test-unlinked-link.php` the existing assertion moves to `<option value="' . $A . '">TEST - Institution 20 (Test)</option>` (Ruling 20). Also: the Institution Dashboard of the test institution, drawn in `bin/test-institutions-dashboard.php`, holds neither mark nor label (Ruling 7).
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** as the table says. A caller printing `mark()` beside escaped text prints it unescaped with `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- mark() escapes its classes, title and word.`; a label goes through `esc_html()` or `esc_attr()` where the name did.
- [ ] **Step 4: Run green, then the checks; commit.** `"Test institutions: a Test mark beside a test institution's name in every list"`.

### Task 4: The queue, the menu bubble, the Administrator Dashboard's tiles and cards, and the reminder digest

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php` (`queue_count()` 2073, `queue_rows()` 2127, `render_queue()` 3094)
- Modify: `includes/modules/class-wpcpm-institution-application.php` (`pending_ids()` beside `pending_count()` at 2600)
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`collect()` 95-246, `counts()` 293-455, `render_applications()` 2090, `render_agreements()` 2283 and `render_agreement_list()` 2353, `render_reports()` 2412, `render_requests()` 2540)
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`remind()` 2331)
- Test: `bin/test-institutions-screen.php`, `bin/test-administrators-dashboard.php`, `bin/test-institution-agreement.php`, `bin/test-semester-report.php`; run `bin/test-overview.php` and `bin/test-ability-answers.php` (both read `counts( collect() )`)

**Interfaces:**
- Consumes: Task 1's `real_application_ids()`, `real_post_ids()`, `count_real_rows()`, `count_real_accounts()`, `label()`.
- Produces `WPCPM_Institution_Application::pending_ids( $limit = 0 ): int[]`: the IDs `pending_count()` counts, which becomes `count( self::pending_ids( $limit ) )`. The two suites' stand-ins of the class gain it.
- Produces each row of `queue_rows()` with `test` (bool), and its `waiting` the real total.

The count sites and what each counts (Rulings 8 to 10):

| Site | Counts |
| --- | --- |
| `queue_count()` | `real_application_ids( pending_ids( $limit ) )`, `real_post_ids( awaiting_review( $limit ), WPCPM_Institution_Agreement::META_INSTITUTION )`, `real_post_ids( open_requests( $limit ), WPCPM_Institution_Request::META_INSTITUTION )` |
| `queue_rows()` `waiting` | the same three over `application_ids( $open_states )`, `$documents` and `$requests` before they are sliced |
| `render_queue()` | the heading is `waiting`; "Showing the oldest N of M", drawn while N is less than M: N the drawn rows with `test` false, M `waiting` |
| `collect()` | `open_total` and `closed_total` through `real_application_ids()`; `$overdue` and `$overdue_request` count only rows not of a test institution |
| `counts()` | `$review` `count_real_rows( awaiting, 'record' )`; `$drafts`, `$due` by `'institution'`; `$requests` by `'institution'`; the locked tile `count_real_accounts( $data['locked'] )` |
| `render_applications()` | the card's count `open_total`; the open "N more" line `open_total` less `count_real_rows( $open, 'post_title' )`; the closed fold's summary `count_real_rows( $closed, 'post_title' )`, its "N more" line `closed_total` less that |
| `render_agreements()`, `render_agreement_list()` | the card's count and each fold's summary by `count_real_rows( ..., 'record' )` |
| `render_reports()`, `render_requests()` | the card's count by `'institution'` |
| `remind()` | Ruling 15: the two counts over the overdue facts not of a test institution; every overdue one listed, its name through `label()` |

- [ ] **Step 1: Write the failing tests.**
  - `bin/test-institutions-screen.php`, "The menu bubble" (**Review Focus 4**): beside its world of three applications, one agreement and two requests (6), the index gains `recTESTINSTITUT01` "TEST - Example Institution" and the world gains an open application titled "TEST - Example Application", an agreement waiting for review for `recTESTINSTITUT01` and an open request for it, both older than the review window: `$module->menu_label()` is still `'Institutions <span class="awaiting-mod count-6"><span class="pending-count">6</span></span>'`; the Waiting for review heading holds `Waiting for review <span class="wpcpm-count">6</span>`; `count( queue_items( render_screen() ) )` is 9, three of them marked. **Review Focus 3**: the index row for `recTESTINSTITUT01` removed and the option `wpcpm_test_institutions` holding it: still 6 and 6, its agreement and request rows still listed, named as the queue names an institution the index no longer holds, and marked. A world of `QUEUE_MAX` (50) real open applications and the test application, the oldest, and nothing else: fifty rows drawn, and the line reads "Showing the oldest 49 of 50." (the drawn rows less the test one, of the real total).
  - `bin/test-administrators-dashboard.php`, a section "Test institutions are listed and not counted" in the same world (**Review Focus 4**): `counts( collect() )` gives `applications` `n` 3, `agreements` `n` 1 with the label "Collaboration Agreements to review, 0 overdue", `requests` `n` 2 with "0 overdue", their sum 6, and the same with the four test items gone; the applications card's count 3 and its list four applications, the agreements card's count 1 and two items, the requests card's count 2 and three items, each test item marked; a rejected application titled "TEST - Example Rejected" leaves the closed fold's summary as it was and is listed in it; reports: one real and one test draft to review, one real and one test pair due: the tile `n` 1 with "1 due for drafting" and the card's count 2; locked: one real and one test institution account: the `locked` tile `n` 1, both named on the Syncs and health card, the test one with "(Test)". The world without any test item gives every number the same.
  - `bin/test-institution-agreement.php`, "remind()": `rec_a` "Universidad Example" and `recTESTINSTITUT01` "TEST - Example Institution", one agreement each waiting 5 days: the subject `[WPCredits] 1 signed agreement is waiting for review`, the body holds `Universidad Example, uploaded ` and `TEST - Example Institution (Test), uploaded `; only the test one waiting: still sent to both Administrators, subject `[WPCredits] 0 signed agreements are waiting for review`.
  - `bin/test-semester-report.php`, "The daily job" (Global Constraints, everything else works; Ruling 12): after its existing checks, `WPCPM_Semester_Report::delete_all()`, the index one institution `recTESTJOBUNIV001` "TEST - Job University" at Confirmed over `$finished`: `due()` lists its pair; `autodraft_tick()` returns 1; `WPCPM_Semester_Report::find( 'recTESTJOBUNIV001', '2026-H1' )` is a `WP_Post`; one `report-drafted` mail to `managers:report_notify` whose subject names "TEST - Job University"; `queue()` lists it.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** as the table says. `queue_count()`'s docblock gains: the three readers' test items are dropped after the bounded read (Ruling 10).
- [ ] **Step 4: Run green, then the checks; commit.** `"Test institutions: the queue, the menu bubble, the Administrator Dashboard and the reminder digest count real institutions only"`.

### Task 5: The Institutions screen's totals from the index

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php`: `counted_rows()` (6418); `render_pipeline()` (4296-4300, 4311-4315); `render_agreements_on_file()` (4700), `render_discrepancies()` (5803), `render_template_versions()` (6202); `render_semester_reports()` (5866); `render_tab_sync()` (2933-2936)
- Test: `bin/test-institutions-screen.php`; run `bin/test-institutions-dashboard.php`, `bin/test-unlinked-link.php`

**Interfaces:**
- Consumes: `WPCPM_Test_Institutions::is_test()`, `real_keys()`, `count_real_rows()` (Task 1).
- Produces `WPCPM_Institutions::counted_rows( array $rows ): array`: the rows at a counted stage and not of a test institution, keys kept; its docblock says both. Every Pipeline count follows it: the total, the member gap, the routing gaps, the consent counts, the agreement gap.

- [ ] **Step 1: Write the failing tests.**
  - The seed's TEST record (Ruling 20): `ck( 'the pipeline counts the fixture rows at a counted stage, the TEST record left out: 86 of its 104', ... array( $seed['counts']['institutions'] - 18, true ) )` in place of the `- 17` check; the Under Review heading counts `$seed['counts']['by_stage']['Under Review'] - 1` (4) while its table lists 5 rows, one marked; each other Pipeline number that held the TEST record (the institutions nobody can act for among them) moves by one, its label saying so.
  - **Review Focus 1**: the index gains `recINSTITUTION003` "Test College" at Confirmed: the total is 87, the Confirmed heading one more, the row unmarked.
  - A folded stage: the TEST record moved to Not Moving Forward: that fold's summary count is the fixture's 14, its table 15 rows.
  - Agreements: a test institution moved to Confirmed by hand, with no agreement recorded, is listed under Agreements on file, marked, and neither that card's sentence nor the Pipeline's "Confirmed with no agreement recorded" counts it; a test institution with an agreement in force is listed under Agreements in force, marked; a discrepancy of its is listed and the "Agreement discrepancies" heading's count leaves it out; a template version signed by a real and a test institution counts 1 and names both, the test one labeled.
  - Semester reports: one real and one test draft: the heading's count 1, both rows listed; with `REPORTS_SHOWN + 1` (61) approved reports, the most recently edited a test one: "Every draft is listed, and the 59 most recently edited approved reports." while sixty are drawn (Ruling 8).
  - Sync and storage: a test institution whose contact address belongs to no member leaves "Contacts who are not members" as it was without it.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** `counted_rows()` filters by stage and through `real_keys()`; a stage's heading and fold count `count( WPCPM_Test_Institutions::real_keys( $group ) )`; `render_agreements_on_file()` counts `$missing` real; `render_discrepancies()` counts `real_keys( $discrepancies )`; `render_template_versions()` counts each version's real institutions; `render_semester_reports()` counts the drafts, and the approved reports drawn, whose `WPCPM_Semester_Report::institution_of()` is not a test institution's (the window's condition, `$approved_total > count( $approved )`, stays on the reports drawn, test ones included, since it says whether the list was cut); `render_tab_sync()` passes `real_keys( $index['rows'] )` to `membership_gaps()`.
- [ ] **Step 4: Run green, then the checks; commit.** `"Test institutions: the Institutions screen's totals leave test institutions out"`.

### Task 6: The rosters and the accounts

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`programs()`, 1839: skip a test institution before its roster is read, Ruling 13)
- Modify: `includes/modules/class-wpcpm-students-sync.php` (`reconciliation()`, near 2355-2440: a Students row whose `institution` is a test one is tallied in none of its numbers, and still listed in `status_disagreement_rows` and `mentored_without_reports`, Ruling 16)
- Modify: `includes/class-wpcpm-accounts-table.php` (`count_view()` 1488: `'exclude' => $this->uncounted_ids()` when it is not empty; a new `protected function uncounted_ids()` answering `array()`)
- Modify: `includes/modules/class-wpcpm-institutions-table.php` (`uncounted_ids()`: the accounts whose `WPCPM_Institution_Members::META_RECORD_ID` is in `test_ids()`, one `get_users()` of IDs, `array()` with no test institution; `count_records()` 777 counts its rows by `count_real_rows( ..., 'id' )`)
- Modify: `includes/modules/class-wpcpm-students-table.php` (`roster()` 455: each row gains `test`, Ruling 9; `institution_counts()` 499 and `status_counts()` 534 pass over rows with `test` true; `extra_tablenav()` prints the test institution's option as `label()` in place of its count; `uncounted_ids()` the roster's IDs with `test` true; Ruling 18)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`render_locked_accounts()` 2963: the count `count_real_accounts( $locked )`)
- Test: `bin/test-administrators-dashboard.php`, `bin/test-students-sync.php`, `bin/test-students-screen.php`, `bin/test-institutions-accounts.php`, `bin/test-accounts-table.php`, `bin/test-institutions-screen.php`; run `bin/test-mentors-screen.php`, `bin/test-sponsors-accounts.php`

**Interfaces:**
- Consumes: Task 1's `is_test()`, `test_ids()`, `count_real_rows()`, `count_real_accounts()`, `label()`.
- Produces `WPCPM_Accounts_Table::uncounted_ids(): int[]` (protected; the mentors' and sponsors' lists keep the default).

- [ ] **Step 1: Write the failing tests**, each world built with and without a test institution, the same numbers in both:
  - `bin/test-administrators-dashboard.php`, "Programs running": `recTESTINSTITUT01` "TEST - Example Institution" with three students in progress on a track and one Graduate who ended this semester: `programs()`'s `tracks`, `finished`, `rows` (no row for it), `quiet` and the phrase "N programs running, N institutions" as without it.
  - `bin/test-students-sync.php`, "The reconciliation card" (the suite requires the real index, the new class and the real sponsors index, and writes the index with `recTESTINSTITUT01`): a Students row of the test institution with no report row, no start date and an address another of its rows holds: `students_without_reports`, `no_start_date`, `duplicate_emails` and `status_disagreements` as without it; a status disagreement of its is still in `status_disagreement_rows`.
  - `bin/test-students-screen.php`, "Status: the column, the picker and the summary say one thing": two student accounts stamped with `recTESTINSTITUT01`, one In Sensei and one Graduate: "Students by status" and the status picker's counts as without them; the institution picker's option reads `TEST - Example Institution (Test)`, no count; chosen, the list lists both; the All, Invited and Never invited counts as without them; their Institution cells marked (Task 3).
  - `bin/test-institutions-accounts.php`: an account acting for `recTESTINSTITUT01`: All, Invited and Never invited as without it, the account listed; a Confirmed test institution with no account: listed on the No account view, marked, and the view's count as without it.
  - `bin/test-accounts-table.php`: `count_view()` sends no `exclude` while `uncounted_ids()` is empty and sends `exclude` with the IDs otherwise.
  - `bin/test-institutions-screen.php`: one real and one test institution account locked: the Accounts tab's notice reads "1 institution account is locked out of roster changes for the rest of today." and names both, the test one labeled.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** as the Files list says. The Invitations cards' buttons are left as they are (Ruling 14).
- [ ] **Step 4: Run green, then the checks; commit.** `"Test institutions: the rosters' figures and the accounts lists leave test institutions out"`.

### Task 7: The abilities and the sponsor report's numbers

**Files:**
- Modify: `includes/class-wpcpm-ability-answers.php` (`program_counts()` 141-144: each stage's group through `real_keys()`, a stage left with none not named; `institutions()` 404-420: `'test' => WPCPM_Test_Institutions::is_test( $match['id'] )` after `'name'`; `institutions_schema()` 1697-1721: `test` after `name`)
- Modify: `includes/modules/class-wpcpm-sponsor-report-numbers.php` (`rows()` 225: the institutions through `real_keys()`, and a roster row naming a test institution passed over)
- Modify: `bin/stubs/ability-readers.php` (the `WPCPM_Sponsors_Index` stand-in gains `is_test_name()`, Ruling 21)
- Test: `bin/test-ability-answers.php`, `bin/test-abilities.php`, `bin/test-sponsor-report.php`; run `bin/test-mcp.php`

**Interfaces:**
- Consumes: Task 1's `is_test()`, `real_keys()`; Task 4's `counts()`; Task 6's `programs()`.
- Produces the answer key `matches[].test` (boolean), required, described as Ruling 17 says.

- [ ] **Step 1: Write the failing tests.**
  - `bin/test-ability-answers.php`: the readers' fixture gains `recINST0000000009` "TEST - Example Institution" at Under Review. "Program counts": `by_stage` is still `array( 'Confirmed' => 2, 'Under Review' => 1, 'Info Sent' => 1, 'No stage' => 1 )`. "One institution": the match keys read `array( 'record_id', 'name', 'test', 'stage', 'country', 'city', 'agreement', 'members', 'students' )`; `test` is `false` for `recINST0000000001`, `true` for `recINST0000000009`, found by its record ID and by "example" alike; the "nothing of a person" walk passes on its answer. A track of a student on its roster is still found by `wpcpm/find-student` (Ruling 17).
  - `bin/test-abilities.php`: `institutions_schema()`'s match properties hold `test` (`type` boolean) and its `required` names it; every answer the suite builds validates against its schema.
  - `bin/test-sponsor-report.php`, "The numbers: the program": a test institution in another country with two students started this term, one finished, ten hours: students started, finished and active, hours, institutions and countries as without it.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** as the Files list says; the schema's description is Ruling 17's sentence.
- [ ] **Step 4: Run green, then the checks; commit.** `"Test institutions: the abilities say which institution is a test and count none, and the sponsor report counts none"`.

### Task 8: The Administrator guide

**Files:**
- Modify: `docs/sections/30-admin-wpadmin.md` (a `#### Test institutions` part at the end of `### Institutions`, before `### Sponsors`), `docs/sections/34-admin-operations.md` (the data table: `wpcpm_test_institutions` in its A to Z place), then `php bin/build-docs.php`
- Test: `bin/test-handbook.php`, `bin/check-references.php`

- [ ] **Step 1: Write the part**, in the guide's voice, one short paragraph each:
  - **The name rule** (since 1.123.55): an institution whose name, trimmed, starts with the word "TEST" in capitals, followed by nothing or by a character that is neither a letter nor a digit, is a test institution: "TEST - Example" and "TEST: Example" are, "Test College", "Contest Labs", "TESTAMENT" and "TEST2 Labs" are not; the rule test sponsors follow. There is no box: the name decides, and renaming the institution in Airtable changes what it is at the next institutions sync.
  - **What is marked**: its application, agreement, requests, semester reports, representatives and students are listed where a real institution's are, with a small "Test" beside the name (or "(Test)" after it in a picker or an email), on the Institutions and Students screens and the Administrator Dashboard; its own Institution Dashboard looks like any institution's.
  - **What is left out**: every count, total, tile and figure: the Administrator Dashboard and the menu bubble, the Institutions screen's totals, the Students screen's counts, the sponsor report's numbers, the reminder digest's counts and the answers Claude access gives (`wpcpm-institution-summary` says `test`); the one exception is an Invitations button, which counts every account it invites. Everything else works as for any institution: its emails, its report's drafting, its agreement, its enrollments and its syncs.
  - **The stage stays**: accepting its agreement never moves its Airtable stage to Confirmed, so the public pages that list Confirmed institutions never show it; keep the shared test institution at Under Review until testing ends.
  - **When testing ends**: a person removes, in Airtable, the test students' rows, their report rows and the test institution's row, and on the site the test representatives' accounts; a test student is never given a mentor, since the mentors' figures count students by mentor. The site remembers the test institution after its row is gone (the option `wpcpm_test_institutions`), so its agreement, requests and reports stay uncounted.
  - The data table's row: `| \`wpcpm_test_institutions\` | \`WPCPM_Test_Institutions::OPTION\` (the record IDs of the test institutions the institutions sync found, remembered after their rows leave Airtable) |`.
- [ ] **Step 2: Build.** `php bin/build-docs.php`, twice; the second changes nothing. Then the checks; `bin/test-handbook.php` and `bin/check-references.php` pass.
- [ ] **Step 3: Commit.** `"Test institutions: the Administrator guide's part on test institutions"`.

### Task 9 (coordinator): The version, the changelog and the whole battery

**Files:** `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot`, `docs/administrators.md`, `docs/build/administrators.html`

- [ ] **Step 1: The version.** 1.123.55 in the plugin header, `WPCPM_VERSION` and the Stable tag; then above `= 1.123.54 =`:

```text
= 1.123.55 =

* An institution whose name starts with the word TEST, in capitals, is a test institution. It, and its application, agreement, requests, semester reports, representatives and students, are listed wherever a real institution's are, with a Test mark beside the name, and left out of every count: the Administrator Dashboard and the menu bubble, the Institutions and Students screens, the sponsor report's numbers, the agreement reminder's counts and the answers Claude access gives, where the institution summary says which institution is a test.
* Accepting a test institution's Collaboration Agreement leaves its Airtable stage as it was, so it never reaches Confirmed and the public pages that list Confirmed institutions never show it. Everything else works as for any institution.
* The site remembers a test institution after its row is removed from Airtable, so what it leaves on the site stays uncounted.
```

  Then `sh bin/make-pot.sh` and `php bin/build-docs.php`.
- [ ] **Step 2: The battery.** `for t in bin/test-*.php; do php $t; done`, then the checks every task ends with, then `php bin/test-fixtures.php`, then Before Task 1's step 3 over the whole diff of the branch (`git diff main --` piped into the three scans in place of the plan). Expected: every suite `ALL PASS`, the suite count as recorded plus one, the scans clean.
- [ ] **Step 3: Commit.** `"Test institutions: 1.123.55, the changelog and the translation template"`.

### Task 10 (coordinator): Main, then the release script's check, build and scan

- [ ] **Step 1: Main holds the branch.** `git checkout main && git merge --no-ff feature/test-institutions -m "Merge 1.123.55: test institutions are listed with a Test mark, left out of every count, and never moved to Confirmed"` with the trailer. If main moved since the branch was made, merge main into the branch first and run the battery there. Then the battery on main.
- [ ] **Step 2: Check, build, scan**, from `~/Projects/wpcredits-release`:

```bash
python3 release.py check plugin
python3 release.py build plugin
python3 release.py scan plugin
```

  Expected: the watch report of the public repository's changes since the last push (read it: a change somebody made in our folders on the mirror is ported first), then `PASSED: check plugin 1.123.55 (...) against live <the live version>.`, `PASSED: build plugin 1.123.55: ...` with the zip's md5, and `PASSED: scan plugin 1.123.55: no finding. ...`. A live version other than 1.123.54 is said to the product owner, since a deploy would ship 1.123.54's changes too. Rebuild `~/GitHub/wpcredits-program-manager.zip` from the build.

### Task 11 (coordinator): The trial on the Docker copy, Airtable faked

No repository files. The kit is written to `.superpowers/sdd/2026-11-05-plugin-1-123-55-test-institutions/trial-kit.md` (under `.superpowers/`, never mirrored), from 1.123.54's kit: signing in as the copy's local Administrator; a placeholder connection (`api_token` `patTRIALNOTAREALTOKEN`, `base_id` `appTRIALBASE00001`); and a must-use plugin `zz-testinst-trial-airtable.php` on `pre_http_request` at priority 1, for `https://api.airtable.com/` only, answering the base's schema, the Countries table empty, the Institutions table from the copy's own index rows as records under the configured field names (less every record ID the option `zz_testinst_trial_removed` holds), a GET of one institution from its index row, and a PATCH with its own body as written, logging every method, URL and body to `site/wp-content/zz-testinst-trial-airtable.log`. The copy's mail stays in its own log. Notes and captures go to the session's scratchpad and are backed up under `.superpowers/session-backups/<the day>/test-institutions/`.

- [ ] **Step 1: Snapshot and read the numbers before.** In `~/Projects/wpcredits-docker`: `bin/snapshot before-trial-1.123.55`, `bin/check | tail -1`, `bin/wp plugin get wpcredits-program-manager --field=version`; then one `bin/wp eval` printing, as numbers only: the menu bubble (`WPCPM_Modules::get( 'institutions' )->menu_label()` stripped to its count), each tile of `WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() )` by key, `count( WPCPM_Institutions::counted_rows( WPCPM_Institutions_Index::rows() ) )`, and how many index rows have a name `WPCPM_Sponsors_Index::is_test_name()` holds for. Expected: at least one such row (the copy holds the live base's TEST institution).
- [ ] **Step 2: Install.** The zip of the version the copy runs in its `release/` folder; then from `~/Projects/wpcredits-release`: `python3 release.py deploy plugin --site local --version 1.123.55` (its plan, `NOT RUN`, exit 3) and the same with `--yes`. Expected: `PASSED: deploy plugin 1.123.55 on http://localhost:8900 ...`.
- [ ] **Step 3: The numbers after.** The same eval: the Pipeline total lower by the test institutions it counted; each tile lower by exactly the test items it counted (the requests tile by the test institution's open request, if it holds one); `WPCPM_Test_Institutions::test_ids()` the TEST rows. In a browser at 1280 pixels: the Waiting for review tab, the Pipeline and the Administrator Dashboard list every test item with its mark, and the bubble, the queue's heading and the tiles agree.
- [ ] **Step 4: The sync and the stage.** The kit's connection and plugin; `bin/wp eval 'WPCPM_Institutions_Sync::start(); while ( WPCPM_Institutions_Sync::is_running() ) { WPCPM_Institutions_Sync::tick( WPCPM_Institutions_Sync::BUDGET_AJAX ); } echo "done\n";'`: `get_option( 'wpcpm_test_institutions' )` holds the TEST record IDs. The sync's provisioning and revoke phases run in the copy as they would on live: whatever they make or detach is undone by the restore, and what they would mail is read in the copy's mail log. As the local Administrator, upload the kit's one-page PDF for the test institution on its Manage members view, then accept it on the Administrator Dashboard: the log holds one PATCH to its record with the five agreement cells and no `Current Stage`, the agreement is accepted and its representatives' acceptance email is in the copy's mail log. Then put its record ID in `zz_testinst_trial_removed` and sync again: the index no longer holds it, the list still does, the queue and the cards still list its items, marked and named by record ID, and the numbers stay as Step 3 read them.
- [ ] **Step 5: Spacing, then put the copy back.** At 1280 and 375 pixels, the mark beside a name in the queue, the Pipeline and a dashboard card: nothing wider than the screen at 375, and the gap between the name and the mark the same as the whitespace flag's (the owner's spacing rule). Then the rollback commands in the release folder's `rollback-local.txt`, `bin/restore before-trial-1.123.55`, the version read again, `bin/check | tail -1`. Expected: the version Step 1 read, `All checks passed.`. Give the product owner the results, the captures and the log's summary, and stop.

### Task 12 (coordinator): Mirror, deploy and the live read, each on the product owner's yes

Every command from `~/Projects/wpcredits-release` unless it says otherwise. Nothing here runs without the product owner's explicit yes for that step.

- [ ] **Step 1: Read live first, sending nothing.** `ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'`: `1.123.54`; anything else, stop and say what it reads (Task 10, step 2). Then the eval of Task 11, step 1, on live, as numbers only: it reads, mails nothing and writes nothing to Airtable (the caches it fills are the ones any page load of the Administrator Dashboard fills).
- [ ] **Step 2: The mirror, on the yes.** `messages/1.123.55.txt`, one plain paragraph with no attribution and no names: "An institution whose name starts with the word TEST is a test institution: it and everything of its are listed with a Test mark wherever a real institution's are and left out of every count the site shows, mails or answers, accepting its agreement never moves its Airtable stage to Confirmed, and the site remembers it after its row leaves Airtable." `python3 release.py mirror plugin --message messages/1.123.55.txt` (`NOT RUN`, exit 3); the product owner is given the version, the md5, the checks' lines, Task 11's results and the message; on the yes, the same with `--yes`. Expected: `PASSED: mirror plugin 1.123.55: origin/trunk is now <the new commit>`.
- [ ] **Step 3: The deploy, on the yes.** `python3 release.py deploy plugin` (its plan, `NOT RUN`), the yes, then `--yes`. Expected: the probes, page 560 republished from `administrators.html`, the caches flushed and purged, `PASSED: deploy plugin 1.123.55 on https://wordpresseducation.org. ...`.
- [ ] **Step 4: Read live back, sending nothing.** The numbers of Step 1 again, and `count( WPCPM_Test_Institutions::test_ids() )`. Expected: each number lower by exactly the test items it held, the test institutions counted, and `wpcpm_test_institutions` absent until the next institutions sync writes it (within three hours). No name or address is printed.
- [ ] **Step 5: The vault and the notes.** The design's first paragraph gains "Shipped as 1.123.55 on <the day>."; the plugin's vault note and the memory note gain the release, and the testing guide for the Administrators (drafted separately) links the guide's *Test institutions* part.
