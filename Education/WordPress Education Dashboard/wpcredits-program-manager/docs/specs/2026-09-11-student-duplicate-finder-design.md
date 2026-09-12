# Student Duplicate Finder

A tool in WPCredits Program Manager that finds students who have more than one row in the Airtable tables Students, Students Reports and Feedback, proposes which rows to delete, and deletes the ones a program manager selects and confirms. It replaces the one-off review list (an HTML page and a CSV) built on 10 September 2026 from a read-only scan.

Status: approved in chat in four parts on 11 September 2026; not built. It targets 1.102.0, the next free minor version at merge time, and deleting ships switched off.

The problem it answers was raised on the WordPress Slack on 10 September 2026: rows that share an address break some of the base's update automations, and the program team asked for a safe way to delete the oldest row of a duplicated student in all three tables, reviewed before anything is deleted.

## 1. Settled by the product owner

| Decision | Settled as |
| --- | --- |
| Review | One program manager ticks rows and presses Review selection; a confirmation screen lists exactly which rows in which tables will go; only a second press deletes them. Not a two-person approval, and not a delete on the first press. |
| Selection | One checkbox per student whose older rows are all clean candidates, selecting that student's proposed rows in all three tables. A student that needs a decision shows a checkbox on each row that may be deleted. Nothing is pre-ticked; Select all ready ticks the clean students. |
| Location | A screen in wp-admin, listed as "Student Duplicate Finder" under Modules, plus a thirteenth tile and a small card on the Administrator Dashboard. |
| Copies | Each deleted row's cells are kept on the site, sealed, for 30 days, then erased by a daily job. A permanent log keeps the table, record ID, created date, status, who deleted it and when, and never a name or an address. |
| Scanning | Every three hours alongside the syncs, plus Scan now. |
| Name | Student Duplicate Finder. |

## 2. What the code and the base say

### 2.1 The three tables

Base `appIzQKfwTn5dyPVp`: Students (`tbla8GZg5x6NY7aWt`), Students Reports (`tbljYkkVGbeoaWEtY`) and Feedback (`tblx3TH6fp4edQJDm`). The three are joined by the email address and by nothing else: the `Students Reports` link column on the Students table is empty on every row.

### 2.2 How the duplicates are made

`Add students to Students Reports and Feedback` (`wflXg1xFuiCSG0pXZ`) creates a Students Reports row and a Feedback row whenever a Students row starts matching its conditions (a name, an email, an institution, a mentor, and one of the statuses it watches). It never checks whether the address already has them. So:

- a student who did not move forward and applies again gets a second Students row and, once it is filled in, a second pair;
- a Students row that stops matching and then matches again (a status set back to In Sensei, a mentor or an institution removed and added again) gets a second pair of its own.

The fix at the source is a Find records step in that automation, creating the pair only when the address has none. The automation belongs to whoever runs the base, so this spec records it as a recommendation (section 13) and does not depend on it.

### 2.3 What the read of 10 September 2026 found

A read-only scan at 11:15 UTC, through the site's own Airtable client:

- Students 1,022 rows (one with no address), Students Reports 1,021, Feedback 1,063;
- 59 addresses with more than one row: 11 in Students, 23 in Students Reports, 58 in Feedback;
- under the review list's rules, which section 5 refines: 30 addresses ready and 29 needing a decision; delete candidates Students 4, Students Reports 4, Feedback 54; rows held for a decision 7, 32 and 12;
- 15 addresses with more report rows than Students rows (either a reused Students row or an older Students row already deleted by hand; the data cannot tell which), 34 duplicated only in Feedback, and 3 with a second Students row and no second report yet.

The Students Reports duplicates, the ones that break the automations, mostly need a person.

### 2.4 What the site keys on Airtable record IDs

- `wpcpm_student_record_id` (user meta): the Students Reports row a student's account is matched to.
- `wpcpm_feedback_record` (user meta): the Feedback row a student's surveys write to. `WPCPM_Student_Feedback::preferred()` chooses it among duplicates by institution.
- `_wpcpm_student_record` on mentor call notes, `_wpcpm_call_student_record` on booked calls, and `_wpcpm_log_subject` on audit entries.
- The roster index holds Students record IDs too, but the syncs rebuild it from the base, so a deleted row simply drops out of it.

The site makes no duplicates of its own: `WPCPM_Student_Feedback::record_for()` creates a Feedback row only when a case-insensitive lookup of the address finds none.

### 2.5 The Airtable client and the API

`WPCPM_Airtable` reads, creates and updates, and has no delete. Its private `request()` already takes an HTTP method, paces requests to five a second, and honors a 429 for every process through `wpcpm_airtable_backoff`. Airtable deletes at most ten records per request.

### 2.6 Airtable's trash

The base trash restores records deleted in the past seven days, on every plan. Airtable's help pages do not say whether records deleted through the API land there (read on 10 and 11 September 2026). The sealed copy (section 8) is what makes a deletion recoverable either way; the first live delete settles the question, and the answer goes into memory.

## 3. The architecture, in ten decisions

1. **A tool, not a module.** `WPCPM_Duplicate_Finder extends WPCPM_Tool`, registered in `WPCPM_Tools::all()`, page slug `wpcpm-tool-duplicate-finder`, and every screen and handler behind `WPCPM_Roles::CAP_MANAGE`. On screen it sits under Modules with the other tools.
2. **The scan is a fifth scheduled sync.** `WPCPM_Duplicates_Scan` copies the shape of `WPCPM_Sponsors_Sync`: a final class with `OPT_STATE`, `OPT_REPORT`, `OPT_LAST`, `OPT_ERROR` and `OPT_LOCK`, a budget of 18 seconds under cron and 8 under AJAX, and `register_cron()`, `schedule()`, `activate()`, `deactivate()`, `start()`, `cancel()`, `is_running()`, `last_read()`, `run_tick()` and `progress()`. It runs on the three-hour schedule 150 minutes after the hour, after students (30), mentors (60), institutions (90) and sponsors (120).
3. **The rules are a pure function.** `WPCPM_Duplicate_Rules::classify()` takes the grouped rows, the site references and a context (the status lists and the work columns) and returns the proposals, with no WordPress and no network, so every rule is tested on fixtures.
4. **A failed or canceled scan never replaces the last good report.** The screen shows the error above the last good list.
5. **The report holds duplicates only.** Addresses with one row in every table are dropped at the end of the scan, and whole tables are never stored.
6. **The report is the menu, not the authority.** Deleting re-reads every selected row and its siblings live and applies the rules again.
7. **No copy, no delete.** The sealed copy is written before the delete, and a site that cannot seal deletes nothing.
8. **Children first.** Within one confirmation, Feedback rows go first, then Students Reports, then Students, so a run that stops midway leaves the Students row standing.
9. **The sync controls are copied, not shared.** Scan now, the progress tick and Cancel exist in `WPCPM_Sync_Module`, which serves modules. The tool carries its own three handlers, in the same order (the capability, then the nonce), rather than refactoring that base: a suite reads `verify()` from that file, and other work runs in the same codebase.
10. **Deleting ships switched off.** A Settings switch, `duplicate_delete_enabled`, off by default and rendered on the Settings screen like `import_enabled`. While it is off, the finder scans and lists, and the Review selection and Delete controls say why they are unavailable.

## 4. The scan

### 4.1 Phases

`students`, `reports`, `feedback`, `finish`. The first three page through their table a hundred rows at a time with every field, because the rules read work and answers (about 32 requests in all). For each row the state keeps a reduced record: the record ID, the created time, the address as typed, the name, the status (Course on Feedback), the institution record IDs, whether a mentor is linked, the start and end dates, the hours (Total hours on Students, Hours on Students Reports), whether Notes is filled, and how many work or answer columns are filled: the rules and the screen only ever ask how many, and about 3,100 rows wait in the scan's state between ticks. Rows with no address are counted and skipped, because they cannot be grouped.

`finish` groups the rows by trimmed, lowercased address; keeps the addresses with more than one row in any table; looks up the site references (every user meta and post meta value that is exactly one of the kept record IDs, with its meta key and, for posts, the post type and status); classifies; and writes the report.

### 4.2 The report

`OPT_REPORT`, autoload off: the read time, the totals per table and the rows with no address, the groups (the address, a display name, the rows with their proposal and reasons, and the flags), and the counts (addresses, ready, needing a decision, and candidates and held rows per table). The screen, the dashboard tile and the delete handler read it, and nothing else does.

### 4.3 Controls

Scan now starts a run and drives it from the screen with the syncs' progress bar, and Cancel stops it. The last error stays on screen until the next good run. A delete is refused while a run is in progress (section 7.4).

## 5. The rules

### 5.1 Per table, per address

Rows are sorted by created time. The only row in a table is kept, and the newest row is kept. Every older row is a delete candidate unless something in 5.2 holds it or 5.3 locks it.

### 5.2 What holds an older row

- A graduation: Graduate or Pending graduation (Students and Students Reports).
- A live status: one of the site's active tracked statuses (`WPCPM_Mentors_Sync::tracked_statuses()`), or Paused (Students and Students Reports).
- Work on a Students Reports row: any column the Student Report Card's report form writes for any track the program map knows (`WPCPM_Student_Report_Form::fields()` for the key `WPCPM_Program::track()` gives each status in `WPCPM_Program::labels()`, which holds the four built-in tracks and every Track Builder track), and Hours above zero.
- Answers on a Feedback row: any filled column other than Name, Email, Course, Institution, Students and the three mentor links (`F1 - Mentor`, `F2 - Mentor`, `F3 - Mentor`). Legacy survey columns count too.
- Total hours or Notes on a Students row.
- An institution link, a mentor, or a status (Course on Feedback) that this row has and the newest row lacks.
- Created in the same second as the newest row.
- The newest row is Not moving forward, Dropped out, SPAM, Duplicated or Fail while this row is live or graduated (Students and Students Reports).

The status rules leave Feedback out because its Course column names a program, not a student's state. A held row is never selected by a student checkbox or by Select all ready. It can be ticked on its own, with its reasons shown beside the checkbox.

### 5.3 What locks a row

A row the site points at (section 2.4) is locked: its checkbox is disabled and carries the reason and what to do instead, for example "A site account uses this row. Delete the other one instead."

### 5.4 Doubts about the newest row

The newest row goes to review, selectable on its own, when it was created in the same second as the row before it; when, in Students or Students Reports, it is Not moving forward, Dropped out, SPAM, Duplicated or Fail while an older row is live or graduated; or when the site uses an older row and not this one.

### 5.5 The verdict

An address is Ready when every older row in every table is a clean candidate and no newest row is in doubt. Otherwise it needs a decision.

### 5.6 Flags

- More Students Reports rows than Students rows: either the automation fired twice for one Students row, or an older Students row has already been deleted by hand.
- More Students rows than Students Reports rows: a newer Students row has no report yet, and gets a pair as soon as it matches the automation's conditions.
- The address is spelled more than one way (case or spaces).

### 5.7 Never

No selection may leave an address with no row in a table.

## 6. The screen

From top to bottom:

1. The header: the name; the last read time and result ("59 duplicated students, read 10 September 2026 at 11:15 UTC"); the next scheduled scan; Scan now and Cancel; and the progress bar while a scan runs. A failed scan shows its error above the last good list. While a scan runs, the list stays visible and Review selection is disabled.
2. Tiles: duplicated students, ready, needing a decision, and rows proposed per table.
3. Ready: one card per student, with the checkbox in the card header reading "Delete the 3 older rows of <name>".
4. Needs a decision: one card per student, with a checkbox on each row that may be deleted and none ticked. Held rows show their reasons; locked rows show a disabled checkbox and the reason.
5. The selection bar, sticky at the bottom: "12 rows selected: Students 4, Students Reports 4, Feedback 4", Select all ready, Clear and Review selection. The form works without JavaScript; the script adds the live count and Select all ready.
6. Deleted rows: the log, newest first. Each entry shows when, who, the table, the record ID, the created date and the status, and either "copy kept until 10 October" with View copy, or "copy erased".

Each card uses the review list's columns: Table, Proposal, Why, Created, Status or Course, Institution, Mentor, Dates, Hours, Work or answers, On the site, and the record ID linked to Airtable.

The confirm screen replaces the list: the rows grouped by student, the totals, the line about the 30-day copy, a red "Delete 12 rows from Airtable" button, and "Back to the list", which keeps the selection. The result notice sits above the list afterwards.

Every checkbox names its student or row for screen readers, and a disabled one points at its reason with `aria-describedby`. Interface text is US English with the full product names.

## 7. Deleting

### 7.1 Review selection

The posted form carries student keys (a hash of the address) and table and record pairs. Each student key expands, from the stored report, to that student's delete candidates; each pair is taken as it is. Anything the report does not mark as selectable is dropped, with the reason shown on the confirm screen. At most 100 rows go in one confirmation.

### 7.2 The confirm screen

The rows by student and table, with the name, address, record ID, created date, status and why; the totals; and the copy line. The Delete button's nonce action is the delete action plus a hash of the sorted table and record pairs, so a token taken from one confirm screen cannot delete a different set.

### 7.3 The delete, in order

1. The capability, then the nonce.
2. The switch is on, and no scan is running.
3. The selection matches the stored report again, by the same expansion as 7.1.
4. A live re-read: for each table, the rows whose lowercased address is one of the selected addresses (the client's `formula_in()` with lowercasing), paged. It returns the selected rows and their siblings together.
5. The checks in 7.4, row by row.
6. The copies: one sealed copy per row about to be deleted, in the state `pending`.
7. The delete: `WPCPM_Airtable::delete_records()` per table, ten per request, Feedback first, then Students Reports, then Students.
8. Each copy whose deletion Airtable confirms becomes `deleted`, and is the log entry from then on.
9. The stored report drops the deleted rows and works out each touched address again, so the list and the dashboard count are right at once.
10. A notice: what was deleted per table, what was refused and why, and until when the copies are kept.

### 7.4 Refusals

Row by row, with the reason in the notice:

- a row the site points at, checked again against the database rather than the report;
- a held row that came in through a student checkbox or Select all ready;
- a row that would leave its address with no row in its table (every selected row of that address in that table is refused together);
- a row that changed since the scan: gone, a different address, newly pointed at by the site, or carrying work or answers it did not have.

As a whole:

- while a scan is running;
- while the switch is off;
- when the site cannot seal (`WPCPM_Secret::can_encrypt()` is false);
- when the live re-read fails.

### 7.5 Partial failure

A batch Airtable refuses stops the run. The rows in it and after it are not deleted, and their copies stay `pending` until the daily job settles them (8.2), so a lost response never leaves a deleted row without its copy, nor a kept row with a log entry saying it went. A 429 stops the run the same way, and the notice says when to try again.

## 8. Copies and the log

### 8.1 The store

One private post per deleted row, post type `wpcpm_dup_copy` (fourteen characters; WordPress silently refuses more than twenty): not public, no UI, not in REST. `post_content` holds the live record (the ID, the created time and the fields) as JSON sealed with `WPCPM_Secret::seal_for_option()`. Meta: the table, the record ID, the created date, the status, the scan's read time, the state (`pending`, `deleted` or `erased`) and the expiry. `post_author` is the manager who deleted it.

### 8.2 The daily job

`wpcpm_duplicates_purge`, once a day, erases the sealed cells of every copy older than 30 days (the state becomes `erased`, and the post and its meta stay as the log), and settles every `pending` copy by reading its record: still in Airtable, the copy is removed, because nothing was deleted; gone, the copy becomes `deleted`.

### 8.3 View copy

While a copy exists, the log offers View copy. It is manager-only, behind a nonce keyed to the copy, and unseals the cells and shows them so a row can be typed back into Airtable by hand. There is no automatic restore: a re-created Students row can make the automation build a new pair, and Airtable gives a re-created row a new ID anyway.

## 9. The Administrator Dashboard

`WPCPM_Administrators_Cards::counts()` gains a thirteenth tile, `duplicates` ("Duplicated students"). Its number is the address count of the stored report, muted at zero like the others, and it anchors to a small card, `wpcpm-duplicates`: "59 duplicated students in Airtable, 30 ready to delete, as of 10 September, 11:15 UTC", with a button to the finder. The card copies the existing cards' rules value for value.

## 10. Privacy, in one place

- The stored report and the screen hold the names and addresses of duplicated students only, for program managers only, and the report is autoload off.
- Copies are sealed (AES-256-GCM) and erased after 30 days, and the log never holds a name or an address.
- This spec, the plan, the fixtures, the guide and the readme hold no student's name or address. The suites use example.test addresses, and the privacy walks cover the new files.
- Uninstall removes the report and state options, the cron events, the switch and every `wpcpm_dup_copy` post.

## 11. Tests

In `bin/`, with synthetic fixtures only:

- `test-duplicate-rules.php`: each rule alone, each with a check that fails when the rule is removed, and the shapes of the 10 September read (two Feedback rows only; two report rows for one Students row; two of everything; a second Students row with no report).
- `test-duplicates-scan.php`: paging, resumable ticks within the budget, the lock, grouping by trimmed and lowercased address, the site-reference lookup, a failed read keeping the last good report, and the schedule at 150 minutes.
- `test-duplicate-delete.php`: the order in 7.3 from step 2; every refusal in 7.4, with nothing written for a refused row (no copy, no log, no delete); children first; and pending copies after a refused batch. The delete is a class of its own, `WPCPM_Duplicate_Delete`, so all of this runs without a request.
- `test-duplicate-finder.php`: the capability before the nonce on every screen and handler, View copy included, which is manager-only; the nonce tied to the exact set; nothing pre-ticked, the two kinds of checkbox, locked rows, the switch and a running scan; the confirm screen and Back to the list; the notice after a delete; and a log without names or addresses.
- `test-duplicate-vault.php`: the copy is never plaintext; the daily job erases at 30 days and keeps the log entry; pending copies are settled both ways; and the post type name is at most twenty characters.
- Extended: `test-airtable.php` (`delete_records()` in batches of ten, and its errors), `test-request.php` (`posted_list()`), `test-roles.php` (the tool registered, and the loader and uninstall require lists kept parallel), `test-settings.php` (the switch rendered), `test-sponsors-sync.php` (five offsets in one cycle), `test-administrators-dashboard.php` and `test-return.php` (the tile, the card and its anchor), `test-handbook.php` (the guide's section), `test-handlers.php` (every new handler), `check-references.php` and `check-standards.sh`.

## 12. Release

- Built in its own worktree off `main`. If other work merges first, this branch rebases and runs every suite again before it merges.
- The version is the next free minor at merge, 1.102.0 as of this spec.
- A section in the program manager guide (`docs/sections/`), then `bin/build-docs.php`.
- The house rules: the version everywhere with a changelog entry, the translation template, the zip, the mirror, and a deploy only on the owner's yes.
- The first live check: a scan on the live site compared with the 10 September read. Then the owner switches deleting on, and the first real delete is the example in the Slack request (its three older rows), checked in Airtable afterwards, the trash included.

## 13. Open items

- Whether Airtable's base trash holds records deleted through the API, settled by the first live delete.
- The Find records step in the creation automation, recommended to the program team. It stops new duplicates, which this tool only clears.
- The 30-day copy period is a constant, and becomes a setting only if the owner asks.

## 14. Deliberately not in scope

- Merging rows (moving answers or work into the kept row): done by hand in Airtable before deleting.
- Automatic restore.
- Editing rows, or setting a status such as Duplicated.
- Other tables, and rows with no address.
- Email alerts about new duplicates: the tile covers it.
