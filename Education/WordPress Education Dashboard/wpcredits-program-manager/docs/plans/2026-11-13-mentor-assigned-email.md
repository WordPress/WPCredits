# The site tells a mentor about a new student: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When the mentors sync finds a current student a mentor has not been told about, the site emails that mentor once, listing every new student; the first run and a switched-off stretch email nobody.

**Architecture:** One new class, `WPCPM_Mentor_Assigned`, owns the remembered set (one option), the detection, the two guards and the send. The mentors sync hands it every mentor's rows at the end of `phase_assign()`. One new email template in the Invitations area and one new yes/no setting.

**Tech Stack:** WordPress plugin PHP (stand-alone suites run as `php bin/test-*.php` from the plugin root), the plugin's mail registry (`WPCPM_Mail`, `WPCPM_Mail_Templates`, `WPCPM_Mail_Catalog`), the guide's builder.

**Spec:** `docs/specs/2026-11-13-mentor-assigned-email-design.md` (approved by the product owner on 13 November 2026). Facts read from the code: `.superpowers/sdd/2026-11-13-mentor-assigned-email/facts.md`.

**Branch and worktree:** `feature/mentor-assigned-email` in `.worktrees/mentor-assigned-email`, based on main `16de0d5c` (1.123.88). Tasks 1 to 3 are built there; before Task 4 the release before it (1.123.89, branch `feature/owner-decisions`) is merged in, because both change the same guide sections. **Version: 1.123.90**, unless a read at Task 5 shows it taken.

## Global Constraints

- Copy: no em or en dash anywhere (plain hyphens); "Administrators" is the one name for the people who run the program, and "program manager" appears in no string, guide or comment added here; full product names ("Mentor Report Card"); US English; no sentence starts with "Please"; no names of real people or schools in a guide, a fixture or a comment (fixtures use invented names at example.org).
- No student's name or address in an email subject, a sync notice or a log line: the mail log keeps subjects, and the sync's report is read by every Administrator.
- Nothing links to an Airtable form.
- Every behavior gets a check that fails first, in the suite its task names. Before each commit the task's suites pass; when a `docs/sections` file changed, `php bin/build-docs.php` twice, then `php bin/test-guide-labels.php`, `php bin/test-guide-jobs.php`, `php bin/test-build-docs.php`, `php bin/test-administrator-guide.php`.
- Settings row help outside a fold is one sentence of at most 160 characters (`bin/test-settings.php` enforces it).
- Workers never ssh, never touch the live site, Airtable, Slack or Docker, never send real mail, never run `git stash`, never push, never print the `wpcpm_settings` option, never dispatch subagents.
- The version changes in Task 5 only.

## Review Focus

1. **The first run after the release**, with every mentor already holding a list: no email, and the set holds every student on every list. Pinned in Task 2, and end to end in Task 3.
2. **A short read, then a full one** (a mentor's rows shrink to nothing in one run and are back in the next): nobody is told again. The same for a mentor made inactive and active again, and for a mentor record that moves to another account. Pinned in Task 2.
3. **A run that dies after an email is sent:** the next run repeats at most that one email. Pinned in Task 2 (the option is written after each send).
4. **The switch off for several runs, then on:** students who arrived meanwhile are not announced. Pinned in Task 2.
5. **Values from the base in a line:** a name with a line break or angle brackets, an empty institution, no start date, an empty name: one well-formed line each, nothing else altered. Pinned in Task 2.

---

### Task 1: The switch, the catalog row and the template

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (the default, the `people` scope list, the guarded flag loop near line 1057)
- Modify: `includes/class-wpcpm-settings-screen.php` (the "Accounts" section, `:1328-1365`, after the invitation emails row)
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (`emails()`, in the Invitations rows, after the mentor's welcome)
- Modify: `includes/mail/templates/invitations.php` (one new entry, with no `eol` key)
- Test: `bin/test-settings.php`, `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-emails-templates.php`

**Interfaces:**
- Produces: setting key `mentor_assigned_email` (bool, default `true`, scope `people`); catalog and template ID `student-assigned-mentor` (label `Student assigned, to the mentor`, module `WPCPM_Mail_Catalog::MODULE_INVITATIONS`, audience `WPCPM_Mail_Catalog::TYPE_MENTOR`); placeholders `site_name`, `count` (required), `students_list` (required, body only), `dashboard_link` (required).

- [ ] **Step 1: Write the failing checks.**
  - `bin/test-settings.php`: the default is `true`; a save of the `people` scope without the key stores `false` and with it stores `true`; a save of another scope leaves it as it was; the key is in the `people` scope's pinned lists (update the "last nine" slice and the `$by_design` list the facts sheet names, in the order the row is drawn); the drawn Accounts section holds, after the invitation emails row, a row labelled `New students for a mentor` with a checkbox named `mentor_assigned_email` whose text is `Email a mentor when a student is assigned to them`, the help `One email per mentor per sync lists every student new to their list, and nobody is emailed about students already on it.` and one closed Details fold named for the row holding exactly the spec's section 7 Details text.
  - `bin/test-mail-catalog.php`: the catalog holds `student-assigned-mentor` with that label, module and audience, placed after the mentor's welcome.
  - `bin/test-mail-templates.php` and `bin/test-emails-templates.php`: the template's trigger is `When the mentors sync finds a current student a mentor has not been told about: one email per mentor per sync, listing every new student.`; its subject is `[{site_name}] {count} assigned to you`; its body is the spec's section 6 body, paragraph for paragraph; `students_list` may not be in a subject; each placeholder has a non-empty example (the list's example is two invented lines in the line format of the spec); a test send of it renders with no placeholder left; the entry has no `eol` key.
- [ ] **Step 2: Run** the four suites. Expected: the new checks fail, the rest pass.
- [ ] **Step 3: Implement** the setting (default, scope, guarded flag), the row (the printf-and-`close_row()` shape of the invitation emails row beside it), the catalog row and the template entry, with the helper closures `invitations.php` already has; add what a list placeholder needs in the way `includes/mail/templates/students.php` does it for `student-quiet-mentor` (`count`, `students_list`, `dashboard_link`).
- [ ] **Step 4: Run** the four suites and `php bin/test-emails-screen.php` if it exists. Expected: all pass. Fix any count or order pin the new row or email moves (say which in the report).
- [ ] **Step 5: Commit:** `Mentors: a switch and an email for telling a mentor about a new student`.

### Task 2: `WPCPM_Mentor_Assigned`, what a mentor has been told

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-assigned.php` (required where the other modules are; `bin/test-roles.php` checks every class is required in the main file and in `uninstall.php`)
- Modify: `uninstall.php` (delete the option, beside `WPCPM_Institution_Announce::OPT_ANNOUNCED`)
- Test: `bin/test-mentor-assigned.php` (new, on the stubs `bin/test-institution-announce.php` and the quiet-students suite use for options, users and mail)

**Interfaces:**
- Consumes: `WPCPM_Settings::get_value( 'mentor_assigned_email', true )`; `WPCPM_Mail::send( $user_id, 'student-assigned-mentor', $build )` (returns bool; `$build` runs in the recipient's language); `WPCPM_Mail_Templates::mail( $id, $values )`; `WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_MENTOR )` with the home URL as the fallback; `WPCPM_Program::label( $status )`; the date helper the Mentor Report Card uses for a start date (`includes/class-wpcpm-dates.php`); `WPCPM_Test_Institutions::is_test( $name )`.
- Produces:
  - `const OPT_TOLD = 'wpcpm_mentees_told';` stored with autoload off as `array( 'v' => 1, 'seeded' => <time>, 'told' => array( <mentor record ID> => string[] ) )`.
  - `const MAX_EMAILS = 25;` and `const BRAKE_MIN = 20;`
  - `public static function run( array $lists, array $options = array() ): array`. `$lists` is mentor record ID => `array( 'user_id' => int, 'rows' => array[] )`, one entry for every mentor the run read, each row a mentee row as the sync stores it (`record_id`, `name`, `email`, `status`, `is_past`, `start`, `institution`). `$options`: `'by_hand'` (bool, default false) and `'alive'` (a callable answering bool, asked before every email after the first; false stops the sending). It answers `array( 'seeded' => bool, 'told' => int, 'students' => int, 'waiting' => int, 'failed' => int, 'braked' => int )`: mentors emailed, students in those emails, mentors left for the next run by the ceiling or a stop, mentors whose email was refused, and mentors the brake held.
  - `public static function line( array $row ): string` one student's line.

- [ ] **Step 1: Write the failing checks** in `bin/test-mentor-assigned.php` (invented people at example.org):
  - **Review Focus 1.** No option stored, three mentors with lists: `run()` sends nothing, answers `seeded` true, and the option holds every row's ID under its mentor, past rows included.
  - A second run with the same lists: nothing sent, `seeded` false.
  - A new current row for one mentor: one email to that mentor's account, `told` 1, `students` 1, and the ID is in the set afterwards. A third run: nothing.
  - Two new rows for one mentor: one email whose body holds two lines, `count` reading `2 students`; one new row reads `1 student`.
  - One student linked to two mentors: each is told once.
  - A row that moves from mentor A to mentor B: B is told, A is not, and A's set is unchanged.
  - A new past row: no email, and it is remembered. That row later current: still no email.
  - A new row whose institution is a test institution: no email, remembered.
  - A mentor with `user_id` 0: nothing sent, nothing remembered for them; given an account in the next run, told then.
  - A mentor absent from the set (new to the site) with two current rows: told about both.
  - **Review Focus 2.** A mentor's rows empty in one run and back in the next: nothing sent. A mentor missing from `$lists` in one run and back in the next: nothing sent. The same mentor record ID with a different `user_id`: nothing sent.
  - A send that answers false: `failed` 1, the IDs are not remembered, and the next run sends again.
  - **Review Focus 3.** With three mentors to tell and the mail stub throwing on the second: the first mentor's IDs are already in the stored option when the exception leaves `run()`.
  - **Review Focus 4.** The setting off: nothing sent and the new IDs are remembered; the setting on in the next run: nothing sent for them.
  - The ceiling: 27 mentors each with one new row, in a field of 60 mentors with students: 25 emails, `waiting` 2, those two not remembered; the next run sends 2.
  - The brake: 60 mentors with students, 31 of them with a new row, `by_hand` false: nothing sent, nothing remembered, `braked` 31. The same with `by_hand` true: 25 sent, `waiting` 6. 19 of 30 mentors with a new row: sent (under `BRAKE_MIN`).
  - `alive` answering false before the second email: one email sent, the rest `waiting`.
  - The email: sent with the catalog ID `student-assigned-mentor`; the subject holds no student's name; the body's link is the Mentor Report Card's address, or the home address when there is no such page.
  - **Review Focus 5.** `line()`: `Ada Example, WordPress Credits Program 150h, Example University, starts 2 December 2026: ada@example.org` for a full row (the track through `WPCPM_Program::label()`, the date through the plugin's date helper); no institution and no start date leave their parts out with no doubled comma; a name with a line break comes out on one line; angle brackets in a name are kept as typed; an empty name reads `A student`.
  - A stored option with another `v`: treated as none (seeded again, nothing sent).
- [ ] **Step 2: Run** `php bin/test-mentor-assigned.php`. Expected: fails (no class).
- [ ] **Step 3: Implement** the class. Order inside `run()`: read the setting and the option; with no option of this version, seed and answer; work out each mentor's new current rows (remembering past and test-institution rows without a send); apply the brake; then mentor by mentor: ask `alive`, stop at the ceiling, send, and on true add the IDs and write the option before the next mentor. With the setting off, remember every new ID in one write and send nothing. The list's lines and `count` are built inside the send's closure, so they are in the mentor's language.
- [ ] **Step 4: Run** `php bin/test-mentor-assigned.php`, `php bin/test-roles.php`, `php bin/test-uninstall.php` if it exists. Expected: all pass.
- [ ] **Step 5: Commit:** `Mentors: what each mentor has been told about, and the email for a new student`.

### Task 3: The mentors sync tells the mentors

**Files:**
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`start()`: the state gains `'by_hand' => ! wp_doing_cron()`; `phase_assign()`, `:2034-2110`; `empty_stats()`)
- Modify: `includes/modules/class-wpcpm-mentors.php` (the run report's labels, about `:990-1000`)
- Test: `bin/test-mentors-sync.php`, `bin/test-mentors-screen.php` if it pins the report's rows

**Interfaces:**
- Consumes: `WPCPM_Mentor_Assigned::run( $lists, array( 'by_hand' => bool, 'alive' => callable ) )` and its answer (Task 2).
- Produces: the run's stat `told` (mentors emailed), labelled `Mentors told about new students` in the report; three notices, each only when its number is above 0:
  - `%d more mentor has new students and is told at the next sync.` / `%d more mentors have new students and are told at the next sync.`
  - `%d mentor could not be emailed about new students; the next sync tries again.` / `%d mentors could not be emailed about new students; the next sync tries again.`
  - `%d mentors would have been told about new students at once, more than half of the mentors who have students, so nobody was emailed. If that is right, start a mentors sync by hand: that run sends them.`

- [ ] **Step 1: Write the failing checks** in `bin/test-mentors-sync.php`:
  - a full run on a site with no stored set: the lists are written as before, no email, the report's `told` is 0;
  - a second full run whose Students Reports hold one more current student for one mentor: one email to that mentor, `told` 1, and the mentor's list holds the student (the list is written before the email is sent);
  - a run started by cron has `by_hand` false in its state and one started through `start()` outside cron has it true; the value reaches `run()`;
  - the three notices appear with their numbers (drive them through a stub of `WPCPM_Mentor_Assigned` or small fixtures) and hold no name or address;
  - a mentor with no account is passed over as before;
  - the lists, the counts and every existing stat of a run are what they were (the suite's existing checks stay green untouched).
- [ ] **Step 2: Run** the suite. Expected: the new checks fail.
- [ ] **Step 3: Implement.** In `phase_assign()`, after the loop that writes the lists, hand every mentor of the run (record ID, `user_id`, rows) to `WPCPM_Mentor_Assigned::run()`, with `alive` wrapping the sync's own `hold_lock()`; add its numbers to the stats and notices. Guard the call with `class_exists()`, as the students sync guards the sessions module.
- [ ] **Step 4: Run** `php bin/test-mentors-sync.php`, `php bin/test-mentor-assigned.php`, `php bin/test-mentors-screen.php`, `php bin/test-students-sync.php`. Expected: all pass.
- [ ] **Step 5: Commit:** `Mentors sync: a mentor is told about students new to their list`.

### Task 4: The guides

Do this task only after `feature/owner-decisions` (1.123.89) is merged into this branch; the controller does the merge.

**Files:**
- Modify: `docs/sections/31-admin-settings.md` (the Accounts entries: one setting entry in the section's shape), `docs/sections/32-admin-tools.md` (the email table: one row; the list of reply addresses if it lists every email), `docs/sections/34-admin-operations.md` (what the mentors sync does, the two guards, and one row for `wpcpm_mentees_told` in "Where the plugin keeps its data"), `docs/sections/63-admin-steps-mentors.md` (the whole path, and the job where a student is given to a mentor), `docs/sections/62-admin-steps-students.md` (moving a student to another mentor: the new mentor is emailed, the former one is not), `docs/sections/20-mentor-card.md` or `25-mentor-checklist.md` (one sentence for the mentor)

- [ ] **Step 1:** Write the entries from the spec, each sentence true to the code of Tasks 1 to 3: when the email goes, what it lists, who is left out, the first run, the switch (bold: `New students for a mentor`), the ceiling of 25 with the notice quoted word for word, the brake with its notice quoted word for word and what starts a sync by hand (name the button exactly as the Mentors screen prints it), and that until the welcome emails Airtable sends are switched off the mentor is also copied on the student's welcome email.
- [ ] **Step 2:** `php bin/build-docs.php` twice; the four guide suites. Expected: all pass.
- [ ] **Step 3: Commit:** `Guides: the email that tells a mentor about a new student`.

### Task 5: The release's own files

- [ ] **Step 1:** Read the version on main and on every branch; take 1.123.90 if free.
- [ ] **Step 2:** `wpcredits-program-manager.php` (header and constant), `readme.txt` (Stable tag; a changelog entry of three or four bullets: the email, the first run emails nobody, the switch, the two guards).
- [ ] **Step 3:** `php bin/build-docs.php` twice; `bash bin/make-pot.sh`.
- [ ] **Step 4:** The whole battery: every `php bin/test-*.php`, `php bin/check-references.php`, `php bin/check-spelling.php`, `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" bash bin/check-standards.sh` (0 errors).
- [ ] **Step 5: Commit:** `1.123.90: the site tells a mentor about a new student`.

## After the tasks

A whole-branch review; a trial on the Docker copy (the first sync emails nobody; one invented student added to a mentor's list is announced once, in the mail log; the switch off and on); a read-only count on the live site of how many mentors hold a list, so the brake's numbers are known before the release; then `release.py` check, build and review. Mirror and deploy only on the product owner's yes.
