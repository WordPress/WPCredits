# Deep check fixes, 1.110.0: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close every open finding of the 22 to 23 September 2026 deep check of 1.109.1 (seven medium, fifty-two low after the two highs shipped as 1.109.2), with the four decisions the product owner gave on 23 September 2026, and ship the result as 1.110.0.

**Architecture:** No new modules. Nine tasks by area, in an order that puts the privacy scrub first and the documents last, each fix landing with a check that fails on the unfixed code and passes after. The findings' text travels with the plan as one brief per task, rendered from the judged list; a task's implementer reads its brief and this plan's rulings, not the reports.

**Tech Stack:** PHP 7.4 compatible WordPress plugin, WordPress 6.5 floor; standalone suites under `bin/test-*.php` that stand WordPress in at their top; `bash bin/check-standards.sh`, `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php`; `bin/build-docs.php` for the guides; no build step.

**Spec:** the judged findings of the deep check (the report at https://claude.ai/artifact/P6Gt2HNjS7sNqVvWt9X3Gv; the structured list `deep-check-judged.json` and the per-task briefs `task-N-findings.md` in the plan's workspace), the specs under `docs/specs/` as this plan amends them in Task 8, and the four decisions of 23 September 2026: a student on no track is drawn the 150-hour track's definition; the typed-name confirmation before columns are created is built; the team member's name leaves the tree and the mirror's history stays; every confirmed item is fixed in one release.

## Global Constraints

- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` never `[]`, strict `in_array()`; PHP 7.4 compatible; every string a person reads in US English; no em dash or en dash anywhere, a plain hyphen; full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- Every code fix lands with a check in the suite the finding names (or a new one where the finding says none), and the implementer proves the check fails on the unfixed code first: write the check, run the suite, see the failure the finding predicts, then change the code, then see it pass. A wording or comment fix needs no check. A check asserts real behavior through the real classes; the handlers suite presses a handler with `run()` and reads its flash from the raw `wpcpm_flash` user meta.
- The battery stays silent after every task: `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR ` and ends `85 warnings, no errors.` or fewer warnings; never run it with `--fix`. Gate traps: a keyed array on one line is refused; a stand-alone `$i++` is refused; a missing `@param` is refused; a placeholder string needs a translator comment; Yoda on a variable against a call.
- `bin/` and `docs/` never ship in the zip but are published on the public GitHub mirror. Nothing personal, no real Airtable record ID, no real profile handle, no email address beyond `example.test`; the fixtures walk in `bin/test-fixtures.php` is the judge and Task 1 makes it stricter.
- Every handler checks login and capability first, then the nonce; every posted value goes through `wp_unslash()` and a sanitizer; every printed value is escaped at output.
- Nothing is deleted in Airtable except where the duplicate finder's spec says so; nothing in this plan touches the live site, ssh, the network or Airtable; no installs; no subagents from an implementer.
- Version numbers move in Task 9 only (plugin 1.110.0). No theme release.
- Comments explain why and name the finding (its id) or the decision behind a rule; a docblock that describes behavior the code does not have is a defect. Every commit message starts with "Deep check fixes:" and names the finding ids it closes, and ends with the `Co-Authored-By:` trailer of whoever made it, after a blank line.

## What this plan decides

1. **Rulings per finding are in each task**, drawn from the reviewers' fixes and the refuters' notes; where they differed, the refuter's note wins, since it was written against the reviewer's.
2. **Parked with their reasons:** BUILDER-2 with TRACKS-4 (moot while all four tracks run from their definitions; a Refresh button waits for the T5 release unless Task 4 finds it a one-liner); the mirror's history (the owner's decision).
3. **The no-track form** is the 150-hour definition through the compiled filter (the owner), pinned by a suite check and written into the Track Builder design's section 8 and T5 row.
4. **The typed confirmation** is built as the design says (the owner): only when columns would be created.
5. **Task order:** 1 (privacy), 2 (sessions), 3 (store and report form), 4 (builder screens), 5 (publishing), 6 (duplicate finder), 7 (uninstall), 8 (documents), 9 (release). Tasks run one after another on one branch, so a later task starts from the earlier task's tree; Tasks 2 and 3 both touch the report form only where the plan names it.

---

### Task 1: Privacy and fixtures: the shipped name, the record ID, the handles, the walks, the fixed dates

**Findings:** SURFACES-5 (low), SURFACES-6 (low), SURFACES-8 (low), TESTS-DOCS-11 (low), TESTS-DOCS-12 (low), TESTS-DOCS-7 (low), TESTS-DOCS-6 (low). Brief: `task-1-findings.md` in the plan's workspace.

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (the comment at about line 98 credits the request by issue number alone), `includes/modules/class-wpcpm-mentors-sync.php` (the comment at about line 753), `bin/test-group-sessions.php`, `bin/test-report-form.php`, `bin/test-student-program.php` (the fixture value and the eight handles), `bin/fixtures/learn-structure-403425.json` (the teacher's user ID), `bin/refused-strings.php` (the name hashed in), `bin/test-fixtures.php` (the ID walk reads `includes/` too; the name walk's instruction names what the anonymizer touches; a handle walk), `bin/anonymize-fixtures.php` (docs/ and includes/ hits, or the instruction changed), `bin/check-spelling.php` (reads the seeds' notes), `bin/test-handlers.php` (dates relative to today), `readme.txt` (the name), `docs/specs/2026-09-14-session-series-design.md` (the name)
- Test: `bin/test-fixtures.php`, `bin/test-tooling.php`

**Rulings:**
- SURFACES-5 and TESTS-DOCS-10 (one item): the team member's full name goes from every file under `includes/`, `bin/`, `docs/` and `readme.txt`; the request is credited as "a mentor's request" and by its issue number (WordPress/WPCredits#166) only; the name is hashed into `WPCPM_SAMPLE_NAMES` with `php bin/anonymize-fixtures.php --hash` so the walk refuses it from now on; the Slack-handle fixture becomes a synthetic handle. The mirror's history is left as it is (the owner, 23 September 2026).
- SURFACES-6: the comment's example becomes `recXXXXXXXXXXXXXX`, and the ID walk in `bin/test-fixtures.php` reads `includes/` beside `bin/` and `docs/`.
- SURFACES-8: the eight profile handles become synthetic (`student-one`, `mentor-two`, and so on) and a walk refuses any handle in a fixture that is not of that shape; nothing reaches the network in a suite.
- TESTS-DOCS-11: the instruction the walks print names what `bin/anonymize-fixtures.php` actually touches, and the anonymizer learns to report (not rewrite) hits under `docs/` and `includes/`, or the instruction says to edit those by hand.
- TESTS-DOCS-12: the Learn structure fixture drops the teacher's WordPress.org user ID; the suite that reads the fixture keeps passing.
- TESTS-DOCS-7: `bin/check-spelling.php` reads the seed definitions' notes under `includes/tracks/seeds/`.
- TESTS-DOCS-6: the handlers suite's planned dates are computed from today (a helper answering a weekday N weeks on), so the suite does not turn red on 5 January 2027; the expected strings are computed the same way.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 2: Group sessions and calls: the calendar version, blocking by span, the re-paired student, the session's own words

**Findings:** SESSIONS-3 (medium), SESSIONS-4 (medium), SESSIONS-5 (low), SESSIONS-7 (medium), SESSIONS-9 (low), SESSIONS-10 (low), SESSIONS-11 (low), SESSIONS-12 (low), SESSIONS-13 (low), SESSIONS-14 (low), SURFACES-3 (low), SURFACES-4 (low), HOTFIX-1 (low), HOTFIX-2 (low), TESTS-DOCS-5 (low), SESSIONS-6 (low), SESSIONS-8 (low). Brief: `task-2-findings.md` in the plan's workspace.

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-calls.php`, `includes/modules/class-wpcpm-group-sessions.php`, `includes/modules/class-wpcpm-call-calendar.php`, `includes/modules/class-wpcpm-mentor-availability.php` (`slots()`), `includes/class-wpcpm-ics.php` (a sequence per event in `build_many()`), `includes/modules/class-wpcpm-mentor-notes.php`, `includes/modules/class-wpcpm-students-sync.php` (the re-pairing and `counts_by_status()`), `assets/js/forms.js` (a reader for `data-wpcpm-confirm`)
- Test: `bin/test-handlers.php`, `bin/test-group-sessions.php`, `bin/test-mail.php`, `bin/test-submit-guard.php`

**Rulings:**
- SESSIONS-3: `META_REVISION` is bumped under the mentor's lock on every join, leave, move and cancel of a session, and every file sent carries the current revision, REQUEST and CANCEL alike, each event of `build_many()` included, so every file a student receives outranks the one before; `calendar()`'s memo key already includes the sequence. A mail check moves twice, then leaves, cancels and re-joins, and asserts each SEQUENCE exceeds the last that student was sent, with the zero-move leave-and-rejoin case.
- SESSIONS-4: the diary answers spans (a sibling of `taken_starts()` answering start and end), `slots()` drops a slot whose span overlaps a taken span, `first_clash()` and `clashes_with_another()` test overlap against the spans; `handle_book()`'s after-write exact-start check stays. Checks: a slot starting inside a session is not offered; a session planned over a booked call at another minute is refused.
- SESSIONS-5 (low): `handle_leave()` and `handle_cancel()` take the mentor's lock (SESSIONS-3 needs it there), and `handle_join()`, `handle_join_series()` and the reminder sweep read the post's status under the lock or skip a trashed post.
- SESSIONS-7: the students sync, when a student's card and the mentor lists agree on a new mentor (a settled re-pairing, the code's own caution), takes the student off the old mentor's upcoming sessions with the leave mail; the student's list draws Leave for every upcoming session they are on, whoever's it is (`handle_leave()` already accepts it); a note judges access by attendance and `handle_note()` surfaces `wpcpm_note_denied`'s message. Checks for the re-paired student's page and the old mentor's note.
- SESSIONS-9: the attendee count a change compares the places against is read under the lock.
- SESSIONS-10: a move clears the session's reminder mark, so the moved session is reminded once more.
- SESSIONS-11: a program manager's move tells the mentor as well as the students.
- SESSIONS-12 and HOTFIX-1: group sessions leave the booked column of the call list (they have their own list with Join, Leave, Change and Cancel), the diary and the reminder say "group session" rather than "Unnamed student" or "Call booked with", and a manager reading a student's page gets Leave on the student's behalf in the sessions list (`acting_student()` already honors the posted student for a manager).
- SESSIONS-14 and SURFACES-4: every session handler checks login and capability before the nonce, as the tools' `verify()` does.
- SURFACES-3: `counts_by_status()` and `revoke_departed()` read each row through `WPCPM_Roles::id_of()`.
- SESSIONS-6 (low): `forms.js` reads `data-wpcpm-confirm` with `window.confirm()` (the live site is a normal browser page) and the two buttons keep it; the submit-guard suite pins the reader.
- SESSIONS-8 (low): documented as the project's accepted primitive in the lock's docblock; no code change.
- SESSIONS-13, HOTFIX-2, TESTS-DOCS-5: the suites prove the room is read under the lock, that `series_members()` drops a trashed member and a capacity-one member, the mentor's copy of a cancellation file and a one-to-one cancellation's files byte for byte, the second student's and a manager's press, and DTEND, ORGANIZER, ATTENDEE and SUMMARY of a calendar event.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 3: The track store and the report form: keys, the hours group, the no-track form, the lesson links

**Findings:** TRACKS-1 (medium), TRACKS-3 (medium), TRACKS-5 (low), TRACKS-6 (low), PUBLISH-LEARN-6 (low), SURFACES-2 (low). Brief: `task-3-findings.md` in the plan's workspace.

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php` (`check()`, `delete()`, `register` with `embeddable => false`), `includes/tracks/class-wpcpm-track-definition.php`, `includes/tracks/class-wpcpm-track-questions.php` (`rematch()`), `includes/modules/class-wpcpm-student-report-form.php` (`fields( '' )`, the checkbox mark, `render_preview()` with the course), `includes/modules/class-wpcpm-students-dashboard.php` (`render_links()` draws the hours box without a course), `includes/class-wpcpm-content-access.php` (`filter_oembed()` refuses a post that is not publicly viewable), `includes/tools/class-wpcpm-duplicate-vault.php` (`embeddable => false` on its type only)
- Test: `bin/test-track-store.php`, `bin/test-report-form.php`, the track suites, `bin/test-content-access.php` or the nearest

**Rulings:**
- TRACKS-1 and BUILDER-1 (one item): `check()` refuses a status, key or label another track holds, drafts included, so New track, Duplicate and the properties Save all refuse; `delete()` deletes no form option (`compile()` already drops the form of a track that leaves the index) and refuses a post in publish status; the store check plants a published sibling on the same key and asserts its form survives, plus a Save onto a draft's key refused.
- TRACKS-3: the rule lives in `check()` and the editor (not in `validate()`, which every `compile()` runs and would silently leave a published track out): the hours group holds Hours alone, and Hours sits in the hours group alone; the hours box is drawn for a track with no course by `WPCPM_Students_Dashboard::render_links()` (an hours section of its own); `render_preview()` takes the course so it draws what the page draws.
- TRACKS-5 (the owner, 23 September 2026): a student on no track (paused, pending graduation) is drawn the 150-hour track's compiled form through the filter, so an edit to that definition reaches them too; `fields( '' )` follows the switched 150-hour definition and a suite check pins it; the spec's section 8 and T5 row say so (Task 8 writes the words).
- TRACKS-6: a checkbox marked Required draws the Required mark.
- PUBLISH-LEARN-6: a course change keeps the lesson link of every question of a lesson, not the first alone (`rematch()` maps by heading for all).
- SURFACES-2: `filter_oembed()` returns false for a post that is not publicly viewable (`is_post_publicly_viewable()`), and both new post types register `embeddable => false` (ignored below 6.8, honored from it).

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 4: The Track Builder's screens and handlers: the Settings save, the typed confirmation, the refusals a person sees

**Findings:** BUILDER-3 (medium), PUBLISH-LEARN-3 (low), BUILDER-4 (low), BUILDER-5 (low), BUILDER-6 (low), BUILDER-7 (low), BUILDER-9 (low), BUILDER-8 (low), TESTS-DOCS-14 (low), BUILDER-2 (low). Brief: `task-4-findings.md` in the plan's workspace.

**Files:**
- Modify: `includes/class-wpcpm-settings.php`, `includes/class-wpcpm-admin.php` (the Settings form posts the list as drawn), `includes/tools/class-wpcpm-track-builder.php`, `includes/tools/class-wpcpm-track-builder-screen.php`, `includes/tools/class-wpcpm-track-editor.php`, `includes/tools/class-wpcpm-track-editor-screen.php`, `assets/js/track-editor.js`, `assets/css/track-builder.css`
- Test: `bin/test-track-builder.php`, `bin/test-handlers.php` (the builder sections), `bin/test-settings.php` or the nearest

**Rulings:**
- BUILDER-3: the Settings form carries the Currently mentoring list as drawn in a hidden field; the save writes `student_statuses` only when the person changed the textarea, merging in what was added since the page was drawn; a save that would drop the status of a track running from its definition is refused naming the track (the design's 7.5); the Track Builder's list flags a live track whose status is missing from the list.
- PUBLISH-LEARN-3 and BUILDER-10 (one item; the owner, 23 September 2026: build it): when the preflight says columns will be created, Publish asks the person to type the track's name into a box and refuses a press whose text does not match; with nothing to create it stays one press.
- BUILDER-4: a background move the store refuses is shown as refused on the screen (the fetch answers the refusal and the row returns), and its notice is not spent on an invisible page.
- BUILDER-5: the properties Save holds a course link back when Learn cannot answer, as `handle_course()` does since 1.107.0, so the guide's sentence becomes true (Task 8 keeps the sentence).
- BUILDER-6: a refused Save keeps the fields the person emptied empty.
- BUILDER-7: Unpublish the definition is not offered on a built-in track its PHP still runs, and the reason a press gets is true.
- BUILDER-9: Duplicate starts the copy with no course and no hours target, as the design says.
- BUILDER-8 and TESTS-DOCS-2 (one item): the builder suite's `current_user_can()` stand-in answers by capability, and a refusal check exists for every handler and the screen.
- TESTS-DOCS-14: `track-builder.css` says what its captions match; two grays and two sizes on one screen become one.
- BUILDER-2 and TRACKS-4 (parked, moot while all four tracks are switched): the list offers Refresh from the plugin on a published built-in track whose PHP changed, since the guide's step 1 says so; a one-line change if it falls out of BUILDER-7's work, else it waits for T5.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 5: Publishing to Airtable and the client: the judged definition, the record of columns, the schema cache

**Findings:** PUBLISH-LEARN-1 (low), PUBLISH-LEARN-2 (low), PUBLISH-LEARN-4 (low), PUBLISH-LEARN-5 (low), PUBLISH-LEARN-7 (low), TESTS-DOCS-4 (low), DUPLICATES-11 (low). Brief: `task-5-findings.md` in the plan's workspace.

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-publish.php`, `includes/class-wpcpm-airtable.php` (the schema cache and the class docblock)
- Test: `bin/test-track-publish.php`, `bin/test-airtable.php`

**Rulings:**
- PUBLISH-LEARN-1: `run()` reads the draft once, preflights that array and hands the same array to `WPCPM_Track_Store::publish()`, which refuses when the stored draft no longer matches (a hash compared under the lock); no save is refused during a publish.
- PUBLISH-LEARN-2: each column is recorded on the post as it lands, one write per column, so a killed run's log and History say what went into the base.
- PUBLISH-LEARN-4: a reports-table setting the schema does not name refuses the preflight with a sentence naming the setting instead of guessing every column missing.
- PUBLISH-LEARN-5: a publish that created columns busts the schema cache, so the track page reads the base afresh.
- PUBLISH-LEARN-7 and TESTS-DOCS-4: the publish suite's stand-in records the whole field payload and refuses a delete; a mutation that deletes a column fails the suite.
- DUPLICATES-11: the class docblock names both write paths.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 6: The Student Duplicate Finder: the delete under a lock, what changed since the scan, a failed reference read, the log

**Findings:** DUPLICATES-1 (low), DUPLICATES-2 (low), DUPLICATES-3 (low), DUPLICATES-4 (low), DUPLICATES-5 (low), DUPLICATES-6 (low), DUPLICATES-7 (low), DUPLICATES-8 (low), DUPLICATES-9 (low). Brief: `task-6-findings.md` in the plan's workspace.

**Files:**
- Modify: `includes/tools/class-wpcpm-duplicate-delete.php`, `includes/tools/class-wpcpm-duplicate-rules.php`, `includes/tools/class-wpcpm-duplicates-scan.php`, `includes/tools/class-wpcpm-duplicate-vault.php`, `includes/tools/class-wpcpm-duplicate-finder.php`, `includes/tools/class-wpcpm-duplicate-finder-screen.php`
- Test: `bin/test-duplicate-delete.php`, `bin/test-duplicate-rules.php`, `bin/test-duplicates-scan.php`, `bin/test-duplicate-vault.php`, `bin/test-duplicate-finder.php`

**Rulings:**
- DUPLICATES-1: `run()` takes a lock after the switch, scan and seal checks and before the report is read, releases it in a `finally`, with a stale takeover of 300 seconds; the screen's notice switch gains `delete-running`; a delete-suite check runs a second `run()` from inside the delete stand-in.
- DUPLICATES-2: the scan's work count and hours (both on every report row already) travel through `expand()`, and `recheck()` refuses `changed` when the live work count or the live hours value is higher, hours for every table; a digest of the held cells (attachments reduced to their IDs) is the fuller rule if it fits the task, else recorded as parked.
- DUPLICATES-3: `refs_for()` answers a `WP_Error` when a query's answer is not an array or `$wpdb->last_error` is not empty (`empty()`, so the suites' stand-in keeps passing); `run()` answers `refs-failed` before any copy is kept and the scan ends through `fail()` keeping the last good list; the notice switch gains the sentence; a check per query.
- DUPLICATES-4: addresses are trimmed where they are grouped and where the live re-read matches them, and the delete suite's stand-in stops trimming for the code.
- DUPLICATES-5: a copy kept for a record that already has a pending copy replaces the older one.
- DUPLICATES-6: a copy the daily job cannot settle is erased at 30 days with a log line.
- DUPLICATES-7: the confirmation applies the last-row rule, so it lists what the press will delete.
- DUPLICATES-8 and TESTS-DOCS-3 (one item), DUPLICATES-9: the delete suite's Airtable stand-in neither trims nor lowercases for the code, and the vault suite's check compares the scan's answer with the copies it made.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 7: Uninstall and the surfaces: the database global, the sweep, the leftovers

**Findings:** TRACKS-2 (medium), SURFACES-7 (low). Brief: `task-7-findings.md` in the plan's workspace.

**Files:**
- Modify: `uninstall.php`, `phpcs.xml.dist` (undefined variables an error)
- Test: `bin/test-uninstall.php` (new: includes `uninstall.php` from inside a function with stand-ins and runs it whole)

**Rulings:**
- TRACKS-2, SURFACES-1 and TESTS-DOCS-1 (one item): `global $wpdb;` after the `WP_UNINSTALL_PLUGIN` guard; a new suite includes the whole file from inside a function with stand-ins for what it calls and asserts it runs to the end and removes what it names; `VariableAnalysis`'s undefined-variable sniff becomes an error in `phpcs.xml.dist` if the gate stays at its baseline otherwise (say in the report what it flagged).
- SURFACES-7: uninstall deletes the publish lock option, the schema transient and the Learn transients (a prefix sweep of `_transient_wpcpm_learn_%` and its timeouts, as the option loop does).

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 8: Documents: the guides, the specs, the readme sentences

**Findings:** DUPLICATES-10 (low), TESTS-DOCS-13 (low), TESTS-DOCS-8 (low). Brief: `task-8-findings.md` in the plan's workspace.

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (the full product name; the two sentences the screen does not bear out; the course-change sentence stays true after Task 4), `docs/sections/34-admin-operations.md` (the Invitations section: the fifteen-minute gap, only the newest link working), `docs/sections/21-mentor-availability.md` and `docs/sections/11-student-booking.md` (a session blocks every slot it covers; a re-paired student is taken off the old mentor's sessions), `docs/specs/2026-09-10-track-builder-design.md` (section 8 and the T5 row: the no-track form; the hours group rule; key uniqueness across drafts; the typed confirmation stands), `docs/specs/2026-09-14-session-series-design.md` (the revision on every file; blocking by span; the re-pairing), `docs/specs/2026-09-11-student-duplicate-finder-design.md` (7.5 as the reviewer words it), the three `docs/*.md` and `docs/build/*.html` (rebuilt)
- Test: `php bin/build-docs.php` changes nothing after the commit; `php bin/check-spelling.php`; `php bin/test-handbook.php`

**Rulings:**
- DUPLICATES-10 and TESTS-DOCS-9 (one item): "Student Report Card" in full; the two sentences say what the screen does.
- TESTS-DOCS-13: the Invitations section says a second set-your-password invitation is not sent within fifteen minutes and only the newest link works.
- TESTS-DOCS-8: the duplicate finder spec's 7.5 says the rows of a refused batch keep their copies pending for the daily job and the copies of rows in a table never sent are removed at once, which is what the code does.
- Every spec sentence a task above changes the truth of is amended in the same words the task uses, dated 23 September 2026, and the guides describe the screens as the tasks leave them.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

### Task 9: Release 1.110.0

**Files:**
- Modify: `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot`

**Rulings:**
- The version moves to 1.110.0 in the header, the constant and the stable tag; the changelog entry names the deep check of 22 and 23 September 2026 and, in the readme's voice, what a person notices: a session blocking every slot it covers, a re-paired student taken off the old mentor's sessions, the calendar entries that follow every change, the Settings save keeping a live track's status, the typed confirmation before columns are created, the duplicate finder's delete under a lock and its truthful confirmation, uninstall completing; `sh bin/make-pot.sh`; the battery, the checkers, the gate; the zip read back. Merge, mirror and deploy are the controller's and the owner's, as the plan's closing says.

- [ ] **Step 1: For each finding in the brief's order: write the check, watch it fail, fix, watch it pass; commit per finding or per group of findings that one change closes.**
- [ ] **Step 2: Run everything** (the battery, the three checkers, the gate) and report their last lines.

---

## After Task 9

- **Merge and mirror, on the product owner's choice:** merge into `main`, run the battery on the result, push the source to the public mirror (`~/GitHub/Plugins/WPCredits-Tracker-mirror`, branch `trunk`, rsync with the usual excludes, the README table's version, the scan of added lines, commit and push as two steps; fetch and rebase if trunk moved).
- **Deploy only on the product owner's yes:** the recorded recipe (stream the zip, md5, keep the previous zip under its version name, install, read the version back, a before-and-after read-only probe taken right before and after the install, purge), and republish the guide pages that changed (558, 559, 560 as the tasks touch them).

## What this plan leaves out

- The mirror's history (the owner's decision of 23 September 2026).
- A true two-step delete lock for the duplicate finder beyond the option primitive (SESSIONS-8's caveat applies; the accepted primitive stays).
- The cell digest for DUPLICATES-2 if Task 6 finds it more than a morning; recorded as parked then.
