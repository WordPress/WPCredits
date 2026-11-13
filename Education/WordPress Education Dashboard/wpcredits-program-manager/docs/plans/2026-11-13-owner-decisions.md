# Five places where the plugin and its words disagreed: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A graduate keeps the Student Report Card; a moved student's one-to-one calls with the old mentor are canceled with an email to both; a school with no stage is left alone; the "leaves the pipeline" choice says what it does; Reinstate is on the Institutions screen's Agreements tab.

**Architecture:** Four small changes in four modules, each with the checks, the screen text and the guide sentences that go with it, then a release task. No new class, no new setting, no new email template.

**Tech Stack:** WordPress plugin PHP (stand-alone suites run as `php bin/test-*.php` from the plugin root), the guide's builder (`php bin/build-docs.php`, run twice).

**Spec:** `docs/specs/2026-11-13-owner-decisions-design.md` (the product owner's answers of 13 November 2026, plus the builder's calls in its section 3).

**Branch and worktree:** `feature/owner-decisions` in `.worktrees/owner-decisions`, based on main `16de0d5c` (1.123.88). **Version: 1.123.89**, unless a read of live, main and the branches at Task 5 shows it taken; then the next free.

## Global Constraints

- Copy: no em or en dash anywhere (plain hyphens); "Administrators" is the one name for the people who run the program, and "program manager" appears in no string, guide or comment added here; full product names ("Student Report Card", "Institution Dashboard", "Administrator Dashboard"); US English ("canceled"); no sentence starts with "Please"; no names of real people or schools in a guide, a fixture or a comment.
- Nothing links to an Airtable form.
- Every behavior change gets a check that fails first, in the suite its task names. Before each commit: the task's suites pass, `php bin/build-docs.php` twice when a `docs/sections` file changed, then `php bin/test-guide-labels.php`, `php bin/test-guide-jobs.php`, `php bin/test-build-docs.php`, `php bin/test-administrator-guide.php`.
- In the Administrator guide (`docs/sections/60-` to `67-`, `30-` to `35-`), bold is the exact name of something on a screen and nothing else; a quoted screen message is the code's string word for word.
- Workers never ssh, never touch the live site, Airtable, Slack or Docker, never run `git stash`, never push, never print the `wpcpm_settings` option, never dispatch subagents.
- The version changes in Task 5 only.

## Review Focus

1. **A sync started in the browser by the old mentor** (or by an Administrator): both people on a canceled call are mailed, nobody is skipped as "the person who pressed", and the email names the site, not that person. Pinned in Task 2.
2. **A run retried after it failed midway:** a call already in the trash is not read again and nobody is mailed twice. Pinned in Task 2.
3. **A past status that is not a graduation** ("Dropped out") still loses the role at `revoke`; an Administrator who is also a graduate is never touched; giving a graduate the role back sends no email. Pinned in Task 1.
4. **A school missing from the index** (deleted in Airtable): still locked and its people detached; only a school that is in the index with an empty stage is spared. Pinned in Task 3.
5. **A hand-made Reinstate post** for an older revoked document, or when an accepted agreement stands: refused as today; an institution whose newest agreement waits for review is not listed. Pinned in Task 4.

---

### Task 1: A graduate keeps the Student role and the card

**Files:**
- Modify: `includes/class-wpcpm-program.php` (new `is_graduation()`), `includes/modules/class-wpcpm-student-feedback.php:466` (its private `is_graduate()` returns the shared answer)
- Modify: `includes/modules/class-wpcpm-students-sync.php` (the row at `:931-945`, `provision_student()` at `:2126-2132`, `revoke_departed()` at `:2338-2413`)
- Modify: `includes/class-wpcpm-settings-screen.php:1316-1324` (the help under "When a student leaves the program")
- Modify: `docs/sections/62-admin-steps-students.md:14`, `docs/sections/34-admin-operations.md:43-45`, `docs/sections/31-admin-settings.md:103`
- Test: `bin/test-students-sync.php` ("Leaving the synced set", `:1323`), `bin/test-student-feedback.php` if it pins the private name, `bin/test-settings.php` if it pins the help

**Interfaces:**
- Produces: `WPCPM_Program::is_graduation( $status ): bool`, true when the status matches `/graduat|complet|finish|pass/i` (the feedback forms' test, moved, same pattern). Each students-sync row gains `'is_graduate' => bool`, true only when `is_past` and `is_graduation( $status )`.

- [ ] **Step 1: Write the failing checks** in `bin/test-students-sync.php`, with the default lists (past: Graduate, Dropped out) and `student_on_inactive` = `revoke`:
  - an account linked to a record at `Graduate` and holding the Student role keeps the role after a full run; its active mark is 0; `revoked` did not count it.
  - an account linked to a record at `Dropped out` and holding the Student role loses it (Subscriber when no role is left); `revoked` counts it.
  - an account linked to a record at `Graduate` that holds only `subscriber` (a graduate revoked before this release) holds the Student role after the run; the run sent no mail (the suite's mail log has the same length before and after).
  - an account that is an Administrator and linked to a `Graduate` record: roles unchanged.
  - an account linked to a record the run did not read: loses the role and the institution stamp, as today.
  - a past status added in the fixture's settings as `Completed 50h`: kept (the test is the name, not the default list).
  - `WPCPM_Program::is_graduation()`: true for `Graduate`, `Completed 50h`, `Finished`, `Passed`; false for `Dropped out`, `Paused`, `''`.
  - the count check's two numbers are what they were with a graduate holding the role (`current_read()` and `linked_active()` unchanged; see `:2459-2475`).
- [ ] **Step 2: Run** `php bin/test-students-sync.php`. Expected: the new checks fail, the rest pass.
- [ ] **Step 3: Implement.** `is_graduation()` in `WPCPM_Program`; the feedback class calls it. The row gets `is_graduate`. In `provision_student()` the role is added to a found account when the row is not past **or** is a graduate (never on an Administrator, as now). In `revoke_departed()`, collect the record IDs of this run's graduates and, for an account whose record is among them, do everything up to and including the active mark 0, then skip the role step. Comments say why in one or two lines.
- [ ] **Step 4: The screen.** The help under the student rule becomes exactly: `A graduate keeps the role and the Student Report Card either way: a status under Past students that reads as graduated, completed, finished or passed. The account itself is never deleted, and their program details are kept.` The two choice labels stay.
- [ ] **Step 5: The guide.** Rewrite the three sentences the facts sheet names so they say: a graduate keeps the Student role and the Student Report Card whatever the setting; who counts as a graduate (the sentence above, in the guide's voice); any other past student, and anyone the run did not read, loses the role at the default. Keep each entry's shape (`31` is a one-item setting entry). Build the docs twice.
- [ ] **Step 6: Run** the task's suites and the guide's four. Expected: all pass.
- [ ] **Step 7: Commit:** `Students: a graduate keeps the Student role and the Student Report Card`.

### Task 2: A moved student's one-to-one calls with the old mentor are canceled

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-calls.php` (new public `cancel_moved()`; `notify_cancelled()` at `:1809-1870` takes a fourth parameter)
- Modify: `includes/modules/class-wpcpm-group-sessions.php:208-253` (`leave_former()`)
- Modify: `includes/mail/templates/calls.php:508-553` (two triggers, two closing sentences)
- Modify: `docs/sections/11-student-booking.md:52-54`, `docs/sections/21-mentor-availability.md:31`, `docs/sections/62-admin-steps-students.md:23`, `docs/sections/63-admin-steps-mentors.md:18`, `docs/sections/32-admin-tools.md:100-101`
- Test: `bin/test-handlers.php` ("A student moved to another mentor (SESSIONS-7)", `:2012-2092`, and beside the one-to-one cancel checks at `:770-815`)

**Interfaces:**
- Produces: `WPCPM_Mentor_Calls::cancel_moved( int $call_id ): string`. `''` when the call was put in the trash and both people were written to; `'gone'` when it is not a live one-to-one call (not the post type, not `private`, or capacity over 1); `'started'` when its start is not after `time()`; `'busy'` when the mentor's booking lock could not be taken.
- Changes: `notify_cancelled( WP_Post $call, array $facts, $revision = null, $moved = false )`. With `$moved` true nobody is skipped as the actor, and `canceled_by` is `the site, because this student now has another mentor` in the mentor's copy and `the site, because you now have another mentor` in the student's (both translatable).
- `WPCPM_Group_Sessions::leave_former( $student_id ): int` now answers the sessions left plus the calls canceled.

- [ ] **Step 1: Write the failing checks** in `bin/test-handlers.php`, in the fixture the SESSIONS-7 checks use (student paired with mentor A, then the card and mentor B's list both name B, and A's list no longer holds the student):
  - a one-to-one call with A tomorrow: after `leave_former()` it is in the trash, `_wpcpm_call_cancelled_by` is 0, the answer counts it, and two mails went out: `call-cancelled-mentor` to A and `call-cancelled-student` to the student, each with a calendar file whose method is CANCEL.
  - the mentor's mail holds `the site, because this student now has another mentor`; the student's holds `the site, because you now have another mentor`; neither holds `an Administrator`.
  - **Review Focus 1:** the same with the current user set to A, then to an Administrator: still two mails, same words.
  - **Review Focus 2:** `leave_former()` a second time: no further mail, answer 0.
  - a one-to-one call with A that started ten minutes ago: left as it is, no mail.
  - the pairing not settled (the card names B but B's list does not hold the student yet): nothing canceled.
  - A's list still holds the student (two mentors): nothing canceled.
  - a one-to-one call with B: untouched.
  - the lock held by another request: the call stays, no mail, and a later `leave_former()` cancels it.
  - a student on no session with one such call: canceled (the early return on "no sessions" is gone).
  - `cancel_moved()` on a group session's ID answers `'gone'` and changes nothing.
  - an ordinary cancel by the student still mails only the mentor and names the student (unchanged behavior, new closing sentence).
- [ ] **Step 2: Run** `php bin/test-handlers.php`. Expected: the new checks fail.
- [ ] **Step 3: Implement** `cancel_moved()` in the order `handle_cancel()` uses: lock, forget the cached copy and re-read, answer `'gone'` for a trashed or foreign post, `'started'` when under way, write `_wpcpm_call_cancelled_by` 0, trash, read the details, unlock, then `notify_cancelled( $call, $facts, null, true )`. In `leave_former()`, walk the student's upcoming calls once: sessions as today, and each capacity-1 call whose mentor is neither the settled mentor nor a mentor whose list still holds the student goes to `cancel_moved()`. Settle the mentor only when there is at least one call of either kind to look at.
- [ ] **Step 4: The templates.** `call-cancelled-mentor`: trigger `When the student or an Administrator cancels a one-to-one call, or the student moves to another mentor.`; closing sentence `The slot is free again and open for booking.` `call-cancelled-student`: trigger `When the mentor or an Administrator cancels a one-to-one call, or the student moves to another mentor.`; closing sentence `You can book another time on your Student Report Card.` Subjects and placeholders stay. Update any suite that pins the old sentences (`grep -rn "book another time" bin/`).
- [ ] **Step 5: The guides.** One or two sentences in each place the facts sheet names: when a student's new pairing has settled, the next students sync cancels their upcoming one-to-one calls with the former mentor, both receive the "Call canceled" email with a calendar file, a call already under way is left, and the student books with the new mentor. In `32`'s email table the two rows' "sent when" cells follow the new triggers. Build the docs twice.
- [ ] **Step 6: Run** `php bin/test-handlers.php`, `php bin/test-group-sessions.php`, `php bin/test-students-sync.php`, `php bin/test-emails-templates.php`, `php bin/test-mail-catalog.php` and the guide's four. Expected: all pass.
- [ ] **Step 7: Commit:** `Calls: a student moved to another mentor has the old mentor's one-to-one calls canceled, and both are told`.

### Task 3: A school with no stage is left alone, and the pipeline choice says what it does

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-sync.php:1508-1586` (`phase_revoke()`, both loops)
- Modify: `includes/class-wpcpm-settings-screen.php:1794-1817` and `:2660`
- Modify: `docs/sections/61-admin-steps-institutions.md:108` and `:129`, `docs/sections/31-admin-settings.md:313-319` and `:561`, `docs/sections/34-admin-operations.md:26`
- Test: `bin/test-institutions-sync.php` (beside "One full run", `:759`), `bin/test-settings.php:4503`

- [ ] **Step 1: Write the failing checks** in `bin/test-institutions-sync.php`, adding to the seed in the test (not to the shared fixture file) one institution with stage `''` that has a stored agreement option and one member, and one stored option plus member for a record that is not in the index:
  - after a full run at `revoke`: the empty-stage school's option is still stored, its member still holds the Institution role and the membership, and `locked` did not count it.
  - **Review Focus 4:** the record missing from the index: option deleted, member detached, as today.
  - a school at a stage outside the list: locked and detached, as today.
  - at `keep`: the empty-stage school's option is still stored.
  In `bin/test-settings.php`, `$remove_then_leave['institution_on_inactive']` expects `Leave its people in place, able to open only its agreement`, and new checks expect the help and both Details strings from the spec's 2.4, word for word, in the drawn Institutions and Advanced tabs.
- [ ] **Step 2: Run** both suites. Expected: the new checks fail.
- [ ] **Step 3: Implement.** In both loops of `phase_revoke()`, a record that is in the index with an empty stage is skipped exactly as one at an active stage is; one comment says an empty stage is not a stage outside the list (the owner's decision of 13 November 2026). Nothing else reads the stage here.
- [ ] **Step 4: The screen.** The four strings of the spec's 2.4, word for word. The first choice, the section's title and its help stay.
- [ ] **Step 5: The guide.** `61:108` and `61:129` say an empty stage is left alone, and what does lock a school (a stage outside the list, or the school gone from Airtable). `31:313-319` carries the new bold choice name and the new help's meaning; `31:561` and `34:26` add that an empty stage has not left. Add one sentence where `31` describes the choice: a school that left and whose stage is then emptied reopens at the next sync for whoever is still attached. Build the docs twice.
- [ ] **Step 6: Run** `php bin/test-institutions-sync.php`, `php bin/test-settings.php`, `php bin/test-institutions-screen.php`, `php bin/test-admin-menu.php` and the guide's four. Expected: all pass.
- [ ] **Step 7: Commit:** `Institutions: a school with no stage is left alone, and the pipeline choice says what it does`.

### Task 4: "Out of force" on the Institutions screen's Agreements tab

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (two small public readers)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`render_tab_agreements()` at `:3026-3033` and its doc comment, a new `render_agreements_out_of_force( array $index )` after `render_agreements_in_force()`)
- Modify: `docs/sections/61-admin-steps-institutions.md:181-193`, `docs/sections/34-admin-operations.md:381`, `docs/sections/30-admin-wpadmin.md:587-601`
- Test: `bin/test-institutions-screen.php` (`:2065-2072`, `:3036-3043`, new checks in "Agreements in force", `:3005`), `bin/test-institution-agreement.php` (`:3654-3668`)

**Interfaces:**
- Produces: `WPCPM_Institution_Agreement::latest_revoked_id( $record ): int` (a public reader of the private `latest_revoked()`), and `WPCPM_Institution_Agreement::reinstate_question( $name ): string`, which answers `Reinstate the agreement with %s? It is back in force at once, and everybody at the institution is emailed.` with the name in place.

- [ ] **Step 1: Write the failing checks** in `bin/test-institutions-screen.php`, with one fixture institution whose summary state is revoked (one revoked document with a note), one with an accepted agreement, and one whose newest document is submitted over an older revoked one:
  - the Agreements tab draws five cards in this order: Agreements on file, Agreements in force, Out of force, Agreement discrepancies, Agreement template.
  - Out of force lists the revoked institution once: its name linked as Agreements in force links a name, the date it was revoked, the note, and one form posting `action` = `wpcpm_agreement_reinstate`, `wpcpm_agreement_post` = the revoked document's ID, the nonce for `wpcpm_agreement_reinstate_{id}`, no `wpcpm_return` field, `data-wpcpm-once`, and a button "Reinstate" whose `data-wpcpm-confirm` is `reinstate_question()` for that name.
  - **Review Focus 5:** the institution with a submitted document over a revoked one is not listed; the one with an accepted agreement is not listed.
  - a test institution in the list carries the mark the other cards give one.
  - with no revoked institution the card says `No agreement is out of force.` and draws no form.
  - a name with markup in it is escaped in the row and in the question.
  In `bin/test-institution-agreement.php`: a Reinstate posted with the Agreements tab as the referer and no Return field lands on that tab with the outcome `agreement-reinstated`; a post for an older revoked document is refused with `agreement-not-revoked`, and one made while an accepted agreement stands with `agreement-reinstate-standing` (if these two are already checked, point to them in the report and add nothing).
- [ ] **Step 2: Run** both suites. Expected: the new checks fail.
- [ ] **Step 3: Implement.** The card follows `render_agreements_in_force()`: the same card markup, heading "Out of force", one line under it: `Agreements an Administrator revoked, with nothing newer accepted or waiting for review. Reinstate puts one back in force and emails everybody at the institution.` Rows come from the index's rows whose `WPCPM_Institution_Agreement::summary()` state is revoked, sorted by name, each with `latest_revoked_id()`; a row whose ID is 0 is left out. The form is the dashboard's (`WPCPM_Administrators_Cards::render_reinstate_form()`) without the Return field, with this screen's button classes (`button`, as the tab's other secondary presses) and the confirm question. No new stylesheet rule unless the row needs one; if it does, use the tab's existing classes first.
- [ ] **Step 4: The guide.** `61:181-193`: Reinstate is on the Institutions screen's **Agreements** tab, under **Out of force**, at any time, and asks first; the Administrator Dashboard's card still offers it while a signed agreement waits. Remove "At other times no screen offers **Reinstate**." `34:381` no longer says "on this page only". `30:587-601` gains the card's entry (what it lists, the button, the question quoted word for word, where the press lands, that a school outside the active stages is closed again by the next institutions sync). Build the docs twice.
- [ ] **Step 5: Run** `php bin/test-institutions-screen.php`, `php bin/test-institution-agreement.php`, `php bin/test-administrators-dashboard.php` and the guide's four. Expected: all pass.
- [ ] **Step 6: Commit:** `Institutions: Reinstate for a revoked agreement, on the Agreements tab under Out of force`.

### Task 5: The release's own files

**Files:**
- Modify: `wpcredits-program-manager.php` (header and constant), `readme.txt` (Stable tag and a changelog entry), `languages/` through `bash bin/make-pot.sh`

- [ ] **Step 1:** Read the version on main and on every branch (`git branch --format='%(refname:short)'` with `git show <branch>:wpcredits-program-manager.php`); take 1.123.89 if free.
- [ ] **Step 2:** The changelog entry, five bullets in the readme's voice, one per decision, each saying what an Administrator now sees. No names, no dashes other than hyphens.
- [ ] **Step 3:** `php bin/build-docs.php` twice; `bash bin/make-pot.sh`.
- [ ] **Step 4:** The whole battery: every `php bin/test-*.php`, `php bin/check-references.php`, `php bin/check-spelling.php`, `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" bash bin/check-standards.sh` (0 errors).
- [ ] **Step 5: Commit:** `1.123.89: graduates keep their card, a moved student's calls are canceled, a school with no stage is left alone, Reinstate on the Institutions screen`.

## After the tasks

A whole-branch review, a trial on the Docker copy (a graduate's card, a moved student's call and both emails in the mail log, a no-stage school through a sync, the Out of force card and its press), then `release.py` check, build and review. Mirror and deploy only on the product owner's yes.
