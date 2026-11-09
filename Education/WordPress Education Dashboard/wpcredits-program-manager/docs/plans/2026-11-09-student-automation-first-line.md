# Student automation, first line: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The site follows every student in progress: a student quiet for 14 days is flagged for their program manager, reminded once with their mentor, and can say "we've already talked"; a new student gets a first-week checklist, a public Start here page and a Team Matcher page; all of it ships switched off, and launches after the mentor survey's final version.

**Architecture:** Three releases of the Program Manager. Release A adds `WPCPM_Student_Activity` (one answer to "when was this student last active, and how"), `WPCPM_Student_Flags` (a daily job that writes a snapshot of quiet students, the Administrator Dashboard card that reads it, the reminders and the weekly email) and the "we've already talked" action. Release B adds `WPCPM_Student_Checklist` (a Student Report Card module) and `WPCPM_Start_Pages` (the Start here and Team Matcher pages). Release C, after the survey, is the final version: the survey's changes, "Links to your work" through the Track Builder, and the switches on.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); standalone suites `bin/test-*.php` (run from the plugin root, each ends `ALL PASS` and exits 0, own stubs plus opt-in `bin/stubs/*.php`); the Docker copy in `~/Projects/wpcredits-docker` (`bin/wp`, `bin/snapshot`, `bin/restore`, `bin/check`); the release script `~/Projects/wpcredits-release/release.py` (`check`, `build`, `scan`, `mirror`, `deploy`; `local-bin` on PATH).

**Spec:** `docs/specs/2026-11-09-student-automation-design.md` (approved 9 November 2026, main 2671ff5a). It binds; read all of it before any task. The code facts this plan names were read on 9 November 2026 into `.superpowers/sdd/2026-11-09-student-automation-first-line/interfaces.md` (file:line for every class used here); read it beside the task.

## Rulings

1. **Base and numbers.** One branch, `feature/student-automation-1`, in the worktree `.worktrees/student-automation-1`, made from main. Release A takes the next free plugin version when its version task runs (1.123.60 on 9 November 2026), Release B the next after it. Another session may take numbers: read main's version and live's (`ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'`) at each version task and take the next free; the coordinator does the ssh read, never a subagent.
2. **The Airtable column "Student report last edited".** Students edit their report through outside forms as well as on the site, so the site's own stamp misses them. One new column in Students Reports, type Last modified time, watching the columns students fill (every published track's question columns, plus Hours and Main Contribution Team), named exactly `Student report last edited`. The coordinator creates it through the Airtable connector on the product owner's yes, after showing the exact field (Task 0); if the API refuses that field type, the product owner creates it in Airtable's interface. The students sync names its columns, and Airtable refuses a request naming a column that does not exist, so **Release A is not deployed to live until this column exists there**. The Docker trial's fake answers it.
3. **Last activity** is the latest of: `WPCPM_Sign_Ins::META_SIGN_IN`, `META_VISIT`; every call or group session of the student that is not trashed, at the later of its booking time and its start when the start has passed, else its booking time; the site stamp `wpcpm_last_report_save`; the Airtable time `wpcpm_report_edited` (from the column of ruling 2); and `wpcpm_student_talked`'s time. When none exists, the clock start answers with kind `start`. The **clock start** is the later of the account's `user_registered` and the program row's `start` date (Internship Start Date) when that parses.
4. **Who is followed:** a student account (`WPCPM_Students_Dashboard::is_student()`) with `WPCPM_Students_Sync::META_ACTIVE` = 1, whose status (`WPCPM_Students_Sync::status_of( get_program() )`) is on a track: `'' !== WPCPM_Program::track( $status )`. That leaves out Paused and Pending graduation (states on no track) and every past status.
5. **The program manager** of a student: `WPCPM_Students_Sync::META_INSTITUTION`, then `WPCPM_Institutions_Index::row()['country']`, then `WPCPM_Countries::routing()['email']`, then the account with that email if it holds `WPCPM_Roles::CAP_MANAGE`. Anything missing gives "no program manager" (user ID 0).
6. **Settings** (scope `people`, the Students and mentors tab, a section "Quiet students"): `student_flags_mode`, a string `off` | `preview` | `on`, default `off`, sanitized by an `in_array` branch in `WPCPM_Settings::write()` falling back to `off`; `student_quiet_days`, an int, default 14, clamped to 7..60. Release B adds `student_checklist_enabled`, a bool, default false, in the flags list at `includes/class-wpcpm-settings.php:1012`.
7. **The snapshot.** The daily job writes option `wpcpm_student_flags` (not autoloaded): `array( 'at' => int, 'rows' => list )`, one row per quiet student. The card reads it and never computes per row. "We've already talked" removes that student's row from the snapshot at once.
8. **One reminder per quiet spell.** A spell is identified by its last-activity time. User meta `wpcpm_student_reminded` = `array( 'for' => <last activity at>, 'at' => <sent at> )`. A reminder is sent only when the mode is `on`, the spell began (its last-activity time) at or after option `wpcpm_student_flags_on_since` (written once, the first time the mode is saved as `on`), the student's institution is not a test institution, and `reminded['for']` is not this spell's time. In `preview` the job applies every rule of this ruling but the on-since one (there is no on-since yet), stores the answer on the row as `would_remind`, and mails nothing.
9. **Mail.** A new mail area `students` (`WPCPM_Mail_Catalog::MODULE_STUDENTS = 'students'`, file `includes/mail/templates/students.php`, added to `WPCPM_Mail_Templates::AREAS`) with three emails: `student-quiet-student` (to the student, `TYPE_STUDENT`), `student-quiet-mentor` (one a day to a mentor listing their students reminded that run, `TYPE_MENTOR`), `student-quiet-weekly` (Mondays, to each program manager with at least one quiet student, `TYPE_ADMIN`). Sent through `WPCPM_Mail::send()` with `WPCPM_Mail_Templates::mail()`, so each is editable and testable in Tools > Emails and logged. Wording is the builder's within the spec's section 7 (friendly, never a penalty, no apology); the final wording comes with the survey (Release C).
10. **The booking link** in the student's email: the Student Report Card's calls module (`WPCPM_Students_Dashboard::page_url()` + `#wpcpm-module-calls`) when the student has a mentor; else the program manager's `calendly` from ruling 5's routing row; else the Student Report Card.
11. **"We've already talked"** is an `admin-post` action `wpcpm_student_talked`, posted with nonce `wpcpm_student_talked_<student id>`, allowed for the student themself, their mentor (`WPCPM_Mentor_Calls::mentor_for_student()` is the current user) or a holder of `CAP_MANAGE`. It writes `wpcpm_student_talked` = `array( 'at' => time(), 'by' => current user ID )`, which is activity (ruling 3), removes the snapshot row, and redirects back. Emails link to the person's own dashboard, where the button is; no link acts without a signed-in person.
12. **Times.** The daily job `wpcpm_student_flags_daily` runs once a day (`'daily'`), first run the next 06:00 UTC, scheduled on `init` priority 20 with a `wp_next_scheduled` guard (the `WPCPM_Student_Registration_Process` pattern) and on activation, cleared on deactivation and in uninstall. The weekly email goes out on the first run whose site-time weekday is Monday, once per ISO week (option `wpcpm_student_flags_weekly` = `'o-W'` string of the week sent).
13. **The checklist audience** (Release B): option `wpcpm_student_checklist_on_since` written the first time `student_checklist_enabled` is saved on. A student sees the card when the account was registered after that time, or their program row's `start` is within the 28 days before it. Self-ticks in user meta `wpcpm_checklist_ticks` (list of keys).
14. **The pages** (Release B): `start-here` (option `wpcpm_start_here_page_id`) and `find-your-team` (option `wpcpm_team_matcher_page_id`), public (no `WPCPM_Content_Access::META_KEY`), made by `WPCPM_Start_Pages::ensure_pages()`, which the settings save calls when `student_checklist_enabled` turns on (hook `update_option_wpcpm_settings`, the `WPCPM_Sponsors_Page::follow_switch()` pattern), and a CLI subcommand `wp wpcredits start_pages`. The Team Matcher page is made only while the block type `contributor-team-matcher/quiz` is registered.
15. **Test institutions.** A student of a test institution (`WPCPM_Test_Institutions::is_test( META_INSTITUTION )`) is listed on the card with `WPCPM_Test_Institutions::MARK_DASHBOARD`, left out of the card's count and tile, of the weekly email's count, and never reminded.
16. **New files are required by hand** in `wpcredits-program-manager.php`, and in `uninstall.php` where uninstall names them. Every new option, user meta and cron hook is removed in `uninstall.php` (`bin/test-uninstall.php` runs it whole).

## Global Constraints

- Test mail on the live site goes only to maciej@a8c.com (plus-addresses allowed); the Docker copy mails nobody (mail captured) and writes nothing to Airtable (faked).
- Student context stays on the site: no outside service, nothing sent to a model.
- What this line stores lives on the site (options and user meta), never in Airtable, except the two columns of rulings 2 and Task 11.
- "Administrators" is the one name in every string; full product names: Student Report Card, Mentor Report Card, Administrator Dashboard; US English; no em or en dash anywhere (`bin/check-standards.sh`); no sentence of the plugin's own starts with "Please".
- New cards follow the Institution Dashboard's card rules and spacing (`WPCPM_Dashboard_Card`, the `SHELL` of `WPCPM_Administrators_Cards`), at full width.
- Each release documents its screens, settings and emails in `docs/sections/` in the same release, rebuilds the guides (`php bin/build-docs.php`), bumps the version in the plugin header, `WPCPM_VERSION`, readme `Stable tag`, a readme changelog entry and the .pot header (`sh bin/make-pot.sh`).
- A mentor is optional everywhere.
- Every suite in `bin/` passes, `php bin/check-references.php` passes, standards and spelling pass, before a task is called done.
- Subagents never use ssh, never touch the live site, Airtable or Slack; the coordinator does, and only on the product owner's yes.

## Review Focus

1. **A student closed since the snapshot** (graduated, paused or dropped after the job ran): the card still lists them until the next run; the reminder step re-checks ruling 4 at send time and skips them (Task 4 test `reminder_skips_closed_since_snapshot`).
2. **The job running twice in one day** (a slow cron, a manual run): no second reminder, no second mentor digest for the same students, no second weekly email that week (Task 4 tests `second_run_sends_nothing`, `weekly_once_per_week`).
3. **A mentor who is also an Administrator or a program manager:** gets the mentor digest and the weekly email, each once; a student whose mentor account is missing gets the reminder alone (Task 4 test `mentor_missing_student_alone`).
4. **A student account with no record or no program row** (left over, half-synced): never flagged, never an error (Task 2 test `no_program_row_not_followed`).
5. **A program start date in an odd format or in the future:** an unparsable date is ignored (the account date stands); a future one keeps the student unflagged until 14 days after it (Task 1 tests `start_unparsable_ignored`, `start_in_future_holds_clock`).

---

### Task 0 (coordinator): base, worktree, ledger, and the Airtable column on the product owner's yes

- [ ] **Step 1:** In the plugin repository's main checkout: `git status --short` clean for tracked files, main at or after 2671ff5a; `git worktree add .worktrees/student-automation-1 -b feature/student-automation-1 main`; run every `bin/test-*.php` there once: each `ALL PASS`.
- [ ] **Step 2:** Create the ledger with `bash scripts/sdd-workspace docs/plans/2026-11-09-student-automation-first-line.md` (the subagent-driven-development skill's script); the interfaces file is already in that workspace.
- [ ] **Step 3 (product owner's yes):** list the student-filled columns of Students Reports from the published tracks on live (read-only: `WPCPM_Tracks` options over ssh) and show the product owner the field: name `Student report last edited`, type Last modified time, the watched columns. On the yes, create it through the Airtable connector; read the table schema back; if the API refuses the type, ask the product owner to create it in Airtable's interface and read it back. Record the field ID in the ledger. This gates Task 6's live deploy, not the build.

### Task 1: Last activity, and the report stamps

**Files:**
- Create: `includes/modules/class-wpcpm-student-activity.php`
- Modify: `includes/modules/class-wpcpm-student-report-form.php` (in `handle_save()`, right after the `is_wp_error( $result )` check, before `self::forget( $record )`)
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`fields()`: `'report_edited' => 'Student report last edited'`)
- Modify: `includes/modules/class-wpcpm-students-sync.php` (`phase_reports()` names `$fields['report_edited']`; the provision step writes its time to user meta)
- Modify: `bin/fixtures/reports-table-fields.json` (add `Student report last edited`)
- Modify: `wpcredits-program-manager.php` (require the new file)
- Test: `bin/test-student-activity.php` (new)

**Interfaces:**
- Produces: `final class WPCPM_Student_Activity` with `const META_REPORT = 'wpcpm_last_report_save'`, `const META_EDITED = 'wpcpm_report_edited'`, `const META_TALKED = 'wpcpm_student_talked'`; `public static function last( $user_id, $now = 0 ): array` returning `array( 'at' => int, 'kind' => 'sign_in'|'visit'|'call'|'report'|'edited'|'talked'|'start' )`; `public static function clock_start( $user_id ): int`; `public static function quiet_days( $user_id, $now = 0 ): int` (whole days since `last()['at']`, 0 when in the future); `public static function stamp_report( $user_id, $time = 0 ): void`.
- Consumes: `WPCPM_Sign_Ins::META_SIGN_IN`, `META_VISIT`; `WPCPM_Mentor_Calls::for_student( $id, false )` and `details()`; `WPCPM_Students_Sync::get_program()`.

- [ ] **Step 1: Write the failing suite** `bin/test-student-activity.php` on the model of `bin/test-sign-ins.php` (own stubs: user meta, users with `user_registered`, a `WPCPM_Mentor_Calls` stand-in returning call posts, a `WPCPM_Students_Sync::get_program` stand-in). Checks, each with `ck()`:
  - `latest_wins`: sign-in at T-20d, visit at T-3d, report stamp at T-10d gives `array( 'at' => T-3d, 'kind' => 'visit' )`.
  - `call_counts_booking_and_held`: a call booked T-30d, started T-2d (past) gives kind `call`, at T-2d; a call booked T-1d starting T+5d gives at T-1d.
  - `trashed_call_ignored`.
  - `talked_counts`: META_TALKED `array( 'at' => T-1d, 'by' => 7 )` gives kind `talked`.
  - `edited_counts`: META_EDITED T-4d over a visit at T-9d gives kind `edited`.
  - `nothing_gives_start`: no activity, registered T-40d, program start '2026-10-01' (parsed) gives kind `start` and the later of the two.
  - `start_unparsable_ignored`: program start 'soon' gives the registration time.
  - `start_in_future_holds_clock`: start T+3d gives `quiet_days()` 0.
  - `quiet_days_whole`: last at T-14d-1h gives 14.
  - `stamp_report_writes_meta`: `stamp_report( 5, 1000 )` stores '1000' under META_REPORT.
- [ ] **Step 2:** `php bin/test-student-activity.php`: FAIL (class missing).
- [ ] **Step 3: Implement** the class per the Interfaces block and ruling 3; times are Unix seconds; read meta with `is_numeric()`; call times from `WPCPM_Mentor_Calls::details()` (`booked`, `start`), skipping `trash`.
- [ ] **Step 4:** In `handle_save()` call `WPCPM_Student_Activity::stamp_report( $student_id )` at the success point (only after Airtable accepted the write). Add `report_edited` to `WPCPM_Mentors_Sync::fields()` and to the reports fetch's named list in `phase_reports()`; where the sync provisions a student (`phase_provision()`), parse the cell (ISO 8601) with `strtotime()` and `update_user_meta( $user_id, WPCPM_Student_Activity::META_EDITED, $time )` when it parses, else leave the meta as it is. Add the column to the fixture.
- [ ] **Step 5:** `php bin/test-student-activity.php`: ALL PASS. Run every suite (`for f in bin/test-*.php; do php "$f" >/dev/null || echo "FAIL $f"; done`): none fails; in particular the sync suites that pin the reports field list are updated to the new list, not weakened.
- [ ] **Step 6: Commit** `git add -A && git commit -m "Student automation: one last-activity answer per student, and the report stamps it reads"`.

### Task 2: Quiet students, the daily job and the settings

**Files:**
- Create: `includes/modules/class-wpcpm-student-flags.php`
- Modify: `includes/class-wpcpm-settings.php` (`defaults()`, `scopes()['people']`, `write()` branches for the two settings, ruling 6)
- Modify: `includes/class-wpcpm-settings-screen.php` (`render_tab_people()`: a section "Quiet students" after the student registration settings, `open_section( __( 'Quiet students' ), <intro>, 'quiet-students' )`, a `WPCPM_Settings_Rows::select_row()` for the mode with labels Off / Preview: Administrators see the card, nothing is mailed / On, and a `number_row()` for the days)
- Modify: `includes/modules/class-wpcpm-students.php` (boot `WPCPM_Student_Flags::init()`; `activate()` schedules, `deactivate()` and `uninstall()` unschedule)
- Modify: `wpcredits-program-manager.php` (require)
- Test: `bin/test-student-flags.php` (new); `bin/test-settings.php` (the two keys, the scope, the anchor)

**Interfaces:**
- Consumes: Task 1's `WPCPM_Student_Activity::last()`, `quiet_days()`.
- Produces: `final class WPCPM_Student_Flags` with `const MODE_OFF = 'off'`, `MODE_PREVIEW = 'preview'`, `MODE_ON = 'on'`, `const CRON_DAILY = 'wpcpm_student_flags_daily'`, `const OPT_SNAPSHOT = 'wpcpm_student_flags'`, `const OPT_ON_SINCE = 'wpcpm_student_flags_on_since'`, `const OPT_WEEKLY = 'wpcpm_student_flags_weekly'`, `const META_REMINDED = 'wpcpm_student_reminded'`; `public static function mode(): string`; `public static function is_followed( $user_id ): bool` (ruling 4); `public static function manager_of( $user_id ): int` (ruling 5); `public static function rows( $now = 0 ): array` (computes, does not store); `public static function snapshot(): array` (`array( 'at' => int, 'rows' => list )`, empty rows when the mode is off); `public static function run_daily( $now = 0 ): void` (Task 2: computes and stores; Task 4 adds the mail); `public static function forget_student( $user_id ): void` (drops the row); `public static function schedule()`, `unschedule()`, `init()`. A row: `array( 'user_id' => int, 'record' => string, 'name' => string, 'days' => int, 'last_at' => int, 'last_kind' => string, 'mentor_id' => int, 'manager_id' => int, 'test' => bool, 'reminded_at' => int, 'would_remind' => bool )`.

- [ ] **Step 1: Write the failing suite** `bin/test-student-flags.php` (stubs as Task 1's, plus options, `get_users`, `get_user_by( 'email' )`, `user_can`, an `WPCPM_Institutions_Index` / `WPCPM_Countries` / `WPCPM_Test_Institutions` stand-in from `bin/fixtures/institutions-index-seed.json`). Checks:
  - `quiet_after_14`: followed student, last activity T-15d: one row, `days` 15.
  - `not_quiet_at_13`: T-13d: no row.
  - `days_setting_used`: `student_quiet_days` 20 and T-15d: no row.
  - `closed_status_not_followed`: statuses Graduate, Paused, Pending graduation, Dropped out: no rows.
  - `no_program_row_not_followed` (Review Focus 4).
  - `manager_by_country`: institution with a country whose contact email belongs to a `CAP_MANAGE` account gives that user ID; a contact with no account, or an account without the capability, gives 0.
  - `test_institution_marked`: a student of `recSEED0000000001` has `test` true.
  - `off_writes_nothing`: mode off, `run_daily()` leaves `OPT_SNAPSHOT` unset.
  - `preview_snapshot_and_would_remind`: mode preview: the snapshot is written; `would_remind` is true for a real student not yet reminded for this spell and false for a test institution's student or one already reminded; nothing in the mail recorder (`bin/stubs/mail-recording.php`).
  - `on_since_written_once`: saving `on` twice keeps the first time.
  - `forget_student_drops_row`.
  - `settings_clean`: `write()` turns `student_flags_mode` 'loud' into 'off' and days 3 into 7, 99 into 60.
- [ ] **Step 2:** run it: FAIL.
- [ ] **Step 3: Implement** per the Interfaces block and rulings 4 to 8 and 12. `rows()` walks student accounts (`get_users( array( 'role' => WPCPM_Roles::ROLE_STUDENT, 'fields' => 'ID' ) )` through `WPCPM_Roles::id_of()`), filters with `is_followed()`, keeps those at or over the threshold, sorted by `days` descending. `OPT_ON_SINCE` is written from an `update_option_wpcpm_settings` handler when the stored mode becomes `on` and the option is absent. The settings screen draws the section and `bin/test-settings.php` gets the keys, the scope and the heading id `quiet-students` (added in Task 5's docs; in this task add the heading `#### Quiet students` to `docs/sections/31-admin-settings.md` with one placeholder sentence so the anchor test passes).
- [ ] **Step 4:** the new suite and every suite: ALL PASS.
- [ ] **Step 5: Commit** "Student automation: quiet students in a daily snapshot, behind an Off, Preview, On switch".

### Task 3: The Administrator Dashboard card, and "we've already talked"

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`collect()` key `quiet_students` => `WPCPM_Student_Flags::snapshot()`; `counts()` tile `quiet_students` => `array( 'label' => <"students gone quiet">, 'n' => <real rows>, 'card' => 'quiet-students' )` among the watch tiles; `public static function render_quiet_students( array $snapshot )`)
- Modify: `includes/modules/class-wpcpm-administrators-dashboard.php` (`groups()`: the card in `watch`, after the existing watch cards)
- Modify: `includes/class-wpcpm-return.php` (`ANCHORS` gains `quiet-students`)
- Modify: `includes/modules/class-wpcpm-student-flags.php` (`public static function handle_talked()` on `admin_post_wpcpm_student_talked`, ruling 11; `public static function talked_form( $student_id, $label = '' ): string`)
- Test: `bin/test-administrators-dashboard.php`, `bin/test-return.php` (pinned lists), `bin/test-student-flags.php` (talked)

**Interfaces:**
- Consumes: Task 2's `snapshot()`, `forget_student()`, the row shape, `MODE_*`.
- Produces: `WPCPM_Administrators_Cards::render_quiet_students( array $snapshot )`; `WPCPM_Student_Flags::handle_talked()`; `WPCPM_Student_Flags::talked_form( $student_id, $label = '' ): string` (escaped form HTML with the nonce, used by Task 4 on the two Report Cards).

- [ ] **Step 1: Failing checks.** In `bin/test-administrators-dashboard.php`: `quiet_card_hidden_when_off`; `quiet_card_groups_by_manager` (the viewer's own students first, then each program manager by display name, then "No program manager"); `quiet_card_row_facts` (name linked to `WPCPM_Students_Dashboard::page_url()` with `wpcpm_student_view`, days, last activity kind and date, mentor name or "No mentor", "Reminded <date>, no reply" or "Would remind" in preview, the talked form); `quiet_card_test_marked_not_counted`; `quiet_tile_counts_real`; the pinned group and renderer lists updated. In `bin/test-return.php`: the anchors list. In `bin/test-student-flags.php`: `talked_by_student`, `talked_by_mentor`, `talked_by_admin` allowed; `talked_by_other_mentor` and `talked_bad_nonce` refused; a talked press writes META_TALKED and drops the snapshot row.
- [ ] **Step 2:** run: FAIL.
- [ ] **Step 3: Implement.** The card copies `render_mentor_engagement()`'s shell: `WPCPM_Dashboard_Card::open( array_merge( self::SHELL, array( 'key' => 'quiet-students', 'title' => <"Students gone quiet">, 'count' => <real rows>, 'open' => false, 'state' => <phrase>, 'action' => null ) ) )`, a table in `self::scroll_open()`, `self::empty_line()` when no rows, `WPCPM_Dashboard_Card::close()`. Draw nothing when the mode is off. The state phrase names the threshold and the snapshot's time.
- [ ] **Step 4:** suites: ALL PASS.
- [ ] **Step 5: Commit** "Student automation: the Students gone quiet card, and we've already talked".

### Task 4: Reminders, the mentor digest, the weekly email, and the button on both Report Cards

**Files:**
- Create: `includes/mail/templates/students.php` (three entries, ruling 9)
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (`MODULE_STUDENTS`, `modules()` label "Quiet students", three rows in `emails()`)
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (`AREAS['students'] = 'students.php'`)
- Modify: `includes/modules/class-wpcpm-student-flags.php` (`run_daily()` sends in `on`, rulings 8, 10, 12, 15)
- Modify: `includes/modules/class-wpcpm-students-dashboard.php` (a one-paragraph notice at the top of the calls module when the viewed student is in the snapshot: how long it has been, the booking link, `talked_form()`)
- Modify: `includes/modules/class-wpcpm-mentors-dashboard.php` (on a mentee's card when that student is in the snapshot: a line and `talked_form()`)
- Test: `bin/test-student-reminders.php` (new, with `bin/stubs/mail-recording.php`); `bin/test-mail-templates.php` and any suite pinning the catalog's ids or areas

**Interfaces:**
- Consumes: Tasks 2 and 3.
- Produces: template ids `student-quiet-student` (placeholders `site_name`, `days`, `mentor_name` (optional), `booking_link` (required), `dashboard_link` (required)), `student-quiet-mentor` (`site_name`, `students_list` (required, body only), `dashboard_link` (required)), `student-quiet-weekly` (`site_name`, `count` (required), `students_list` (required, body only), `card_link` (required)).

- [ ] **Step 1: Failing suite** `bin/test-student-reminders.php`. Checks:
  - `on_reminds_student_and_mentor`: one quiet student with a mentor: one `student-quiet-student` to the student, one `student-quiet-mentor` to the mentor naming the student; META_REMINDED `for` = the spell's time.
  - `spell_before_on_since_not_reminded`: last activity before `OPT_ON_SINCE`: listed, no mail.
  - `second_run_sends_nothing` (Review Focus 2).
  - `new_spell_reminds_again`: activity after the reminder, then 14 quiet days: a second reminder.
  - `mentor_digest_one_per_mentor`: three quiet students of one mentor in a run: one mentor email listing three.
  - `mentor_missing_student_alone` (Review Focus 3).
  - `booking_link_choice`: with a mentor, the calls module link; no mentor and a country with `calendly`: that link; neither: the Student Report Card.
  - `test_institution_never_mailed`.
  - `reminder_skips_closed_since_snapshot` (Review Focus 1).
  - `weekly_on_monday_once`: a Monday run mails each program manager with rows one `student-quiet-weekly` (count of real rows); a second Monday run that week mails nothing; a Tuesday run mails nothing; a program manager with only test rows gets none.
  - `preview_mails_nothing`.
- [ ] **Step 2:** run: FAIL.
- [ ] **Step 3: Implement** the templates (a `trigger` line each; subjects `[{site_name}] ...`; bodies in short paragraphs, the student one opening with how long and closing with the booking link and the dashboard link; no em dash, no "Please" at a sentence start), the catalog rows and area, and the sending in `run_daily()`: compute rows, store the snapshot, then in `on` send per rulings 8 and 15, re-checking `is_followed()` at send time, stamping META_REMINDED before each send; then one digest per mentor, each student a line linking to them on the Mentor Report Card (`add_query_arg( 'wpcpm_student', $record, WPCPM_Mentors_Dashboard::page_url() ) . '#' . WPCPM_Mentor_Notes::anchor( $record )`); then the weekly step (ruling 12), each student a line linking to the card's row. Take a `WPCPM_Lock` (`wpcpm_student_flags_lock`, 600 s) around the whole run, released in `finally`.
- [ ] **Step 4:** the new suite and every suite: ALL PASS; `php bin/check-references.php` passes.
- [ ] **Step 5: Commit** "Student automation: reminders to the student and the mentor, the mentor digest and the weekly email".

### Task 5: Guides, uninstall, and Release A's version

**Files:**
- Modify: `docs/sections/31-admin-settings.md` (the "Quiet students" settings, replacing Task 2's placeholder), the Administrator Dashboard section among `docs/sections/30-*.md` (the card: who is followed, the 14 days, what counts as activity, the program manager, Preview, the talked button), the emails section (the three emails), `docs/sections/10-student-card.md` (the notice and the button), the mentor card section in `docs/sections/20-*.md` (the line, the button and the digest)
- Modify: `uninstall.php` (options `wpcpm_student_flags`, `wpcpm_student_flags_on_since`, `wpcpm_student_flags_weekly`; user meta `wpcpm_last_report_save`, `wpcpm_report_edited`, `wpcpm_student_talked`, `wpcpm_student_reminded`; `wp_clear_scheduled_hook( WPCPM_Student_Flags::CRON_DAILY )`; the lock)
- Modify: version places and changelog (ruling 1; changelog line "Quiet students: flags, reminders and we've already talked, switched off.")
- Test: `bin/test-uninstall.php`, `bin/test-handbook.php`, `bin/test-settings.php`, `bin/test-admin-menu.php`

- [ ] **Step 1:** write the sections (plain US English, no dashes, "Administrators", full product names); `php bin/build-docs.php`; run it again: no change (idempotent).
- [ ] **Step 2:** uninstall entries; `php bin/test-uninstall.php`: ALL PASS.
- [ ] **Step 3:** version (coordinator gives the number), changelog, `sh bin/make-pot.sh`; every suite, `php bin/check-references.php`, `sh bin/check-standards.sh`: pass.
- [ ] **Step 4: Commit** "Release A: version, changelog, guides and uninstall".

### Task 6 (coordinator): Release A, trial and deploy, each on the product owner's yes

- [ ] **Step 1:** final whole-branch review for Tasks 1 to 5 (the subagent-driven-development skill's final reviewer); fixes; merge to main.
- [ ] **Step 2:** `release.py check plugin`, `build plugin`, `scan plugin`: PASSED.
- [ ] **Step 3: Docker trial.** `bin/snapshot before-student-automation-a`; install the build with `release.py deploy plugin --site local --version <n> --yes`; Airtable faked by a must-use plugin answering the reports table with `Student report last edited` values and logging every request; mail captured. Read: mode off draws nothing and runs nothing; preview: the card at 1280 and 375 pixels, its groups, the would-remind marks, nothing in the mail log; on (with `OPT_ON_SINCE` set to a time before some spells and after others): the right reminders, one mentor digest, the weekly email on a Monday run (the job run with a pinned `$now`), the talked button from each of the three places. Spacing measured against the Institution Dashboard's cards (the owner's spacing rule). Then the rollback and `bin/restore before-student-automation-a`; `bin/check | tail -1`: All checks passed.
- [ ] **Step 4:** report to the product owner with captures; on the yes, and only once Task 0's column exists on live, `release.py mirror plugin --message <paragraph>` then `--yes`, `release.py deploy plugin` then `--yes`. The mode stays off.
- [ ] **Step 5 (product owner's yes):** set the mode to preview on live (`WPCPM_Settings::patch()` over ssh, read back with `get_value()`; nobody saves the Students and mentors tab meanwhile); after the next daily run, read the snapshot's size and the card; nothing in the mail log from it.

### Task 7: The first-week checklist card (Release B)

**Files:**
- Create: `includes/modules/class-wpcpm-student-checklist.php`
- Modify: `includes/modules/class-wpcpm-students-dashboard.php` (`MODULE_START = 'start'`, first in `modules()` with the label "Your first weeks", a `case` in `render_module_body()`)
- Modify: `includes/class-wpcpm-settings.php`, `includes/class-wpcpm-settings-screen.php` (`student_checklist_enabled`, ruling 6, a checkbox row in the "Quiet students" section or a section "New students" with its own anchor)
- Test: `bin/test-student-checklist.php` (new); `bin/test-students-dashboard.php` or the suite that pins `modules()`

**Interfaces:**
- Consumes: Task 1's `WPCPM_Student_Activity::META_REPORT`, `META_EDITED`; `WPCPM_Sign_Ins::has_signed_in()`; `WPCPM_Mentor_Calls::for_student()`; `get_program()['profile']`, `['team']`; `WPCPM_Program::course_url()`; Task 8's `WPCPM_Start_Pages::matcher_url()` (returns '' until Task 8; call it through `class_exists`/`method_exists`).
- Produces: `final class WPCPM_Student_Checklist` with `const META_TICKS = 'wpcpm_checklist_ticks'`, `const OPT_ON_SINCE = 'wpcpm_student_checklist_on_since'`, `const ACTION_TICK = 'wpcpm_checklist_tick'`; `public static function applies( WP_User $student ): bool` (ruling 13); `public static function items( WP_User $student ): array` (list of `array( 'key', 'label', 'done' => bool, 'by' => 'site'|'student', 'link' => string )`, keys `profile`, `signed_in`, `report`, `call`, `team`, `slack`, `channel`, `learn`); `public static function render( WP_User $student, WP_User $viewer ): string`; `public static function handle_tick()`.

- [ ] **Step 1: Failing suite.** Checks: `applies_new_account`, `applies_recent_start` (start 20 days before on-since), `not_applies_old_student` (start 60 days before), `not_applies_switch_off`; each site item done from its source (`profile_done`, `signed_in_done`, `report_done_by_stamp_or_edited`, `call_done_any_call`, `team_done`); `student_ticks_slack_channel_learn` (the handler accepts only those three keys, for the student or a `CAP_MANAGE` viewer, with its nonce); `all_done_folds` (the markup is one summary line with the list inside a closed `<details>`); `learn_links_course`.
- [ ] **Step 2:** FAIL. **Step 3:** implement; the card uses the Student Report Card's existing card markup and spacing. **Step 4:** suites ALL PASS. **Step 5: Commit** "Student automation: the first-week checklist on the Student Report Card".

### Task 8: The Start here page and the Team Matcher page (Release B)

**Files:**
- Create: `includes/modules/class-wpcpm-start-pages.php`
- Modify: `includes/class-wpcpm-cli.php` (`public function start_pages( $args, $assoc_args )`, docblock with `## EXAMPLES` `wp wpcredits start_pages`)
- Modify: `includes/mail/templates/invitations.php` (`invite-student`: optional placeholder `start_here_link` and one line before the Student Report Card line, left out when empty), `includes/class-wpcpm-mail.php` (`welcome_email()` passes it)
- Modify: `includes/modules/class-wpcpm-students-dashboard.php` (the updates module's `render_resources( 'student', $extra )` gains the Start here link)
- Modify: `includes/modules/class-wpcpm-student-registration-form.php` (the confirmation message gains the Team Matcher link when the page exists)
- Test: `bin/test-start-pages.php` (new); the suites pinning `invite-student` and the CLI command list

**Interfaces:**
- Produces: `final class WPCPM_Start_Pages` with `const OPT_START = 'wpcpm_start_here_page_id'`, `const OPT_MATCHER = 'wpcpm_team_matcher_page_id'`, `const SLUG_START = 'start-here'`, `const SLUG_MATCHER = 'find-your-team'`, `const MATCHER_BLOCK = 'contributor-team-matcher/quiz'`; `public static function ensure_pages(): array` (`array( 'start' => int, 'matcher' => int )`); `public static function start_url(): string`; `public static function matcher_url(): string` (both '' unless published); `public static function follow_switch( $before, $after )` (on `update_option_wpcpm_settings`); `public static function starting_content( $which ): string`.

- [ ] **Step 1: Failing suite.** Checks: `ensure_makes_both_public` (published pages, no access-level meta, slugs as above); `ensure_adopts_existing_slug`; `ensure_idempotent`; `matcher_skipped_without_block`; `start_content_blocks` (paragraph and list blocks only, linking the Student guide, the Student Report Card and the Team Matcher page; no dash); `matcher_content_has_block`; `switch_on_makes_pages`; `welcome_has_start_link` and `welcome_without_page_unchanged`.
- [ ] **Step 2:** FAIL. **Step 3:** implement on the model of `WPCPM_Student_Registration_Form::ensure_page()` and `WPCPM_Sponsors_Page::follow_switch()`. The Start here page's first content is short: what to do in the first week (the checklist's items as steps), where the Student guide and the Student Report Card are, how to reach a mentor or a program manager; the program lead's copy replaces it in Release C. **Step 4:** suites ALL PASS. **Step 5: Commit** "Student automation: the Start here and Team Matcher pages, linked from the welcome email, the Student Report Card and the registration form".

### Task 9: Guides, uninstall, and Release B's version

- [ ] As Task 5, for Tasks 7 and 8: the Student guide (the checklist, the Start here page, the Team Matcher), the Administrator guide (the switch, the pages, the CLI command), `uninstall.php` (`wpcpm_checklist_ticks`, the two page-ID options, `wpcpm_student_checklist_on_since`; pages are left in place, as every other page the plugin makes), version and changelog ("New students: the first-week checklist, Start here and the Team Matcher page, switched off."). Commit "Release B: version, changelog, guides and uninstall".

### Task 10 (coordinator): Release B, trial and deploy, each on the product owner's yes

- [ ] As Task 6, Steps 1 to 4, for Release B, with the checklist and the two pages read at 1280 and 375 pixels on the Docker copy (the switch on there, then put back). On live the switch stays off; the pages are made only when it is turned on (Release C).
- [ ] **Roadmap:** once Releases A and B are on live, update `~/GitHub/wpcredits-roadmap/roadmap.html` (the student automation item: releases, dates, the launch in the first week of December), republish the private page and sync the github.a8c.com Project, as on 6 November 2026, on the product owner's yes.

### Task 11 (coordinator, after the survey's results): the final version and the launch

- [ ] **Step 1:** with the product owner and the program lead, settle from the survey: the reminders' wording and recipients, the 14 days, the checklist's items, the Start here copy (the program lead's). Write those changes as a short plan of their own (Release C) and build it the same way.
- [ ] **Step 2 (product owner's yes):** "Links to your work": in the Track Builder, on every published track with an hours part, add a question with column `Links to your work`, type `textarea`, group `hours`, label "Links to your work", help "One link per line: Trac tickets, GitHub pull requests, translations, profile pages."; Publish creates the Airtable column (long text) through `WPCPM_Track_Publish::run()`; read the schema back.
- [ ] **Step 3 (product owner's yes):** the launch: `student_flags_mode` on, `student_checklist_enabled` on (the pages are made), read back; the first daily run's snapshot; the mail log after it (no reminders for spells before the switch, ruling 8).
- [ ] **Step 4:** update the roadmap with the launch, the vault's Now page and the memory note of the plugin.
