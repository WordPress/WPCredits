# Mentor engagement (plugin 1.122.26, main): design

Approved by the owner on 15 October 2026. The reference figures of 15 October stay out of this public copy.

> [!summary]
> Administrators see, per mentor and without a developer, when each mentor last signed in and visited, how many students they have now, whether their schedule is bookable, the notes they wrote and the calls they held. The view is a new **Engagement** tab on the wp-admin Mentors screen, plus a **Mentor engagement** card on the Administrator Dashboard. A read-only command, `wp wpcredits mentor_engagement`, feeds one line of the biweekly #wpcredits-admins update. The site starts recording sign-ins and visits for every account in this release, because today it records neither.

## Why

On 15 Oct 2026 the owner asked how many active mentors on wordpresseducation.org actually sign in and use the site (schedules, call notes). The answer took a Jetpack activity-log export and five ad hoc probes: the plugin keeps no sign-in record and no engagement report. The probes, the export-derived rows and the script that holds every definition are in `wpcredits-program-manager/.superpowers/session-backups/2026-10-15/mentor-activity/` (`analyze.py`, `probe.php` to `probe5.php`, `rows.json`, `data3.json`, `data4.json`, `logins.json`). This design makes that answer part of the plugin.

Related: Two-factor authentication, Module screens spec (plan B, main), WPCredits Program Manager (plugin).

## The owner's decisions (15 Oct 2026), not to be re-asked

| # | Question | Answer |
|---|---|---|
| D1 | Where the view lives | A new **Engagement** tab on the wp-admin Mentors screen, with the per-mentor table, plus a **Mentor engagement** card on the Administrator Dashboard with the headline figures and a link to the tab. |
| D2 | What "signed in within 7 / 30 days" measures | Record **both** the completed sign-in and the day of the latest signed-in visit. The 7 / 30-day figures count visits, so a mentor on a remembered session (14 days) is not shown as absent. |
| D3 | History before the release | Seed every account from what the site already holds, and import the 15 Oct Jetpack-derived last sign-in for each mentor once during the deploy. Dates before the release are marked approximate. |
| D4 | Whose sign-ins are recorded | **Every account.** Only the mentor view reads them for now. |
| D5 | How the figures reach the biweekly update | A read-only WP-CLI command, plus one line the `wpcredits-biweekly-update` task adds to each draft. The task change is shown to the owner before it is saved; every post still needs the owner's yes. |
| D6 | How the view reads | Live on every page load. Nothing is stored except the two sign-in keys. |

Sections 1 to 6 below were approved one by one in the same conversation. Points this written spec makes more exact than the chat did are marked **(made exact here)**.

## 1. Recording sign-ins and visits

**New class `WPCPM_Sign_Ins`** (`includes/class-wpcpm-sign-ins.php`), booted always, like `WPCPM_Two_Factor`: it is not a module and has no switch.

**Two user meta keys, on every account:**

| Key | Holds | Written |
|---|---|---|
| `wpcpm_last_sign_in` | Unix time (UTC) the last sign-in **completed** | on each completed sign-in |
| `wpcpm_last_visit` | Unix time of the first signed-in page view of the latest day, the day read in the site's time zone (`wp_date( 'Y-m-d' )`) | at most once per account per day |

### Sign-in

**What the live Two Factor plugin does (0.16.0, read in the Docker copy's `class-two-factor-core.php`).** The brief said Two Factor fires `wp_login` again after the code step. It does not:

- It hooks `wp_login` at `PHP_INT_MAX` (line 123), and its `wp_login()` (lines 855 to 866) destroys the password session, clears the cookies, prints the code form and calls `exit` for any account using two factors.
- So **every other `wp_login` callback runs after the password and before the code**, whether or not the code is ever entered.
- A finished second step sets the cookie (line 1649) and fires **`two_factor_user_authenticated`** (line 1659). Nothing in the plugin fires `wp_login` a second time.

**The rule:**

1. On `wp_login` (priority 10): when Two Factor is active (`WPCPM_Two_Factor::available()`) and `Two_Factor_Core::is_user_using_two_factor( $user->ID )` is true, record nothing: the code step is still owed. Otherwise record the sign-in.
2. On `two_factor_user_authenticated`: record the sign-in.
3. Both go through one public method, `WPCPM_Sign_Ins::record_sign_in( $user_id, $time = 0 )`, which also writes the visit. A future WordPress.org sign-in module (see WordPress.org sign-in (feasibility, 4 October 2026)) calls the same method.
4. A request records a sign-in at most once (a static guard), so a later Two Factor release that did fire `wp_login` again after `two_factor_user_authenticated` would still write one value.

`is_user_using_two_factor()` reads the enabled providers through the `two_factor_enabled_providers_for_user` filter, which `WPCPM_Two_Factor::enabled_providers()` fills by role, so this class asks the same question Two Factor itself asks before showing the code form.

Never recorded: a failed password, a sign-in abandoned at the code step, an application-password REST request (no `wp_login`).

### Visit

- Checked on `template_redirect` (front-end page views; REST requests end at `parse_request` and never reach it) and on `admin_init` (wp-admin), for a signed-in account.
- Skipped when `wp_doing_ajax()` (so the wp-admin heartbeat does not count), `wp_doing_cron()`, WP-CLI, or `XMLRPC_REQUEST`. **(made exact here: the two hooks)**
- Costs no query: WordPress has already loaded the account's whole user meta when it read the account's roles. The one write happens only when the stored day differs from today.
- An Administrator opening a mentor's Mentor Report Card is a visit by the Administrator, never by the mentor.

### Privacy and clean-up

- The privacy notice (page 654, still a draft) gains one sentence: the site records when you last signed in and when you last visited, and Administrators can see it.
- Deleting an account deletes both keys, as WordPress deletes all user meta.
- `uninstall.php` deletes both keys for everyone, and the two options of section 6.

## 2. The reader, and what each figure means

**New class `WPCPM_Mentor_Engagement`** (`includes/modules/class-wpcpm-mentor-engagement.php`, part of the Mentors module). Read-only. It reads counts and dates, **never a note's text or a call's topic**. The tab, the card and the command all read through it, so they cannot disagree. Every rule is the one in the 15 Oct analysis script (`analyze.py`, kept with the session backups, outside the repository).

**Interface** **(made exact here)**:

- `report( $now = 0 )`: reads the site once (`read()`) and returns `compute()` of it.
- `read()`: the raw facts, as plain arrays: the mentor accounts with their meta, the notes and the calls with the meta keys below. Two SQL reads for the posts and their meta, no `GROUP_CONCAT` (MySQL cuts it at 1,024 characters).
- `compute( array $accounts, array $notes, array $calls, $now )`: pure; returns `array( 'rows' => ..., 'summary' => ... )` with the keys below, so every rule is tested without a database.
- `views()` and `in_view( array $row, $view )`: the tab's views (section 3).

**Who gets a row:** every account with the Mentor role (`wpcpm_mentor`), plus any account that holds a mentor record ID (`wpcpm_mentor_record_id`).

**Row keys and rules:**

| Key(s) | Rule |
|---|---|
| `id`, `name`, `login`, `email` | the account (email only for search, never printed in the table) |
| `active` | `wpcpm_mentor_active` = `1` (Airtable says Active) |
| `staff` | the account also holds the `administrator` role |
| `students_now`, `students_past` | `wpcpm_mentee_count`, `wpcpm_mentee_past_count` (written by the nightly mentors sync; a missing key reads 0) |
| `last_sign_in`, `last_visit` | the two keys of section 1, 0 when absent |
| `last_seen` | the later of the two |
| `seen_7`, `seen_30` | `last_seen` within 7 or 30 days of `$now` |
| `schedule_saved` | the `wpcpm_mentor_availability` meta exists and is an array |
| `weekly_hours` | `WPCPM_Mentor_Availability::is_published()`: what "bookable" meant on 15 Oct |
| `meeting_link` | `WPCPM_Mentor_Availability::get()['meeting_url']` is not empty |
| `notes`, `notes_30`, `last_note` | `wpcpm_mentor_note` posts whose `post_author` is the mentor, any status but `auto-draft` (notes are `private`; a deleted note is gone for good); how many in the last 30 days; the newest one's GMT date |
| `calls_booked`, `calls_held`, `calls_upcoming` | one-to-one calls: `wpcpm_mentor_call` with `_wpcpm_call_mentor` = the mentor and no `_wpcpm_call_capacity`; booked = not in the trash (a cancelled call is trashed); held = booked and `_wpcpm_call_start` has passed; upcoming = booked and not yet started |
| `cancelled_by_mentor` | calls of either kind (one-to-one or group) in the trash whose `_wpcpm_call_cancelled_by` is the mentor, as `analyze.py` counted them |
| `calls_noted` | held one-to-one calls for which the mentor wrote a note whose `_wpcpm_student_record` is one of the call's `_wpcpm_call_student_record` values, dated no earlier than one day before the call's start |
| `sessions_created` | group sessions (a call with `_wpcpm_call_capacity`) on the mentor's calendar whose `post_author` is the mentor |
| `sessions_held`, `sessions_upcoming` | group sessions on the mentor's calendar, not in the trash, started or not yet |
| `sessions_noted` | held group sessions that a note names in `_wpcpm_note_session` |
| `acted` | took a program action: `notes` > 0, or `schedule_saved`, or `sessions_created` > 0, or `cancelled_by_mentor` > 0. A booking made by a student does not count. |

**Summary keys** (the population is the 15 Oct one):

| Key | Rule |
|---|---|
| `active` | rows that are `active` and not `staff` |
| `signed_in` | of those, `last_seen` > 0 |
| `seen_30`, `seen_7` | of those, seen in 30 / 7 days |
| `never` | of those, `last_seen` = 0 |
| `acted` | of those, `acted` |
| `idle` | of those, signed in and not `acted` |
| `with_students` | of those, `students_now` > 0 |
| `with_students_hours`, `with_students_link`, `with_students_notes`, `with_students_seen_30` | of those with students: `weekly_hours`; `weekly_hours` and `meeting_link`; `notes` > 0; `seen_30` |
| `calls_held`, `calls_upcoming`, `calls_noted` | **every** one-to-one call on the site, whoever the mentor, as on 15 Oct **(made exact here)** |
| `sessions_held`, `sessions_upcoming`, `sessions_noted` | every group session on the site |
| `staff` | rows that are `active` and `staff`, counted apart |

After the release, `seen_30` and `seen_7` count visits as well as sign-ins (D2), so they can read higher than the sign-in-only figures of 15 Oct. That is the intent.

**Cost.** About five grouped queries per read (users with their meta primed, one query over the notes, one over the calls with their meta pivoted, as `probe3.php` did), at today's size of about a hundred mentors and a few hundred calls and notes. No cache.

## 3. The Engagement tab (wp-admin)

- **Where:** the Mentors screen, tabs **Accounts | Engagement | Sync**; `WPCPM_Mentors::TABS` gains `engagement` between the two; address `admin.php?page=wpcpm-mentors&tab=engagement`.
- **Who:** the Mentors screen's own capability. Read-only: no form, no handler, no nonce.
- **Above the table:** the headline figures of section 2, as a short list in the same words the dashboard card uses.
- **The table:** a `WP_List_Table` filled from `WPCPM_Mentor_Engagement::rows()`. With about 100 rows it sorts and filters in PHP, as the Accounts tab's `by_value()` already does for Students and Status. Whether it extends `WPCPM_Accounts_Table` or `WP_List_Table` directly is the plan's call: it has no checkboxes, bulk actions or invitation views.

| Column | Shows |
|---|---|
| Mentor | name; row actions **Edit** (the account's editor, as the Accounts tab words it) and **View page** (the mentor's Mentor Report Card) |
| Status | "Active" or "Not active", and "Administrator" when `staff` |
| Students | now, with "N before" under it |
| Last visit | date, and "N days ago" |
| Last sign-in | date |
| Schedule | "Weekly hours, meeting link" / "Weekly hours, no link" / "Saved, no hours" / "Not set" |
| Notes | count, and the last one's date |
| Calls | one-to-one held / upcoming, and "N of M noted" |
| Group sessions | held / upcoming |

- **Sorting:** Mentor, Students, Last visit, Last sign-in, Notes, Calls. Opens on Students now, most first, then by name.
- **Screen Options:** every column but Mentor can be hidden; rows per page, 20 by default (user option `wpcpm_mentor_engagement_per_page`).
- **Search:** name, username, email.
- **Views** **(made exact here: each follow-up view's count equals a headline figure)**:

| View | Rows |
|---|---|
| All | every row |
| Active | `active` and not `staff` |
| With students now | Active, `students_now` > 0 |
| Never signed in | Active, `last_seen` = 0 |
| Not seen in 30 days | Active, signed in before, not seen in 30 days |
| No program action | Active, not `acted` |
| Students, no weekly hours | With students now, not `weekly_hours` |
| Administrators | `staff` (Administrators who also mentor) |

- **Note under the table:** "Sign-ins before <date> are approximate: they were read from the site's sessions, Two Factor and the activity log on the day of the release." The date is the option of section 6.
- **Left out:** a CSV download. The command covers exports; easy to add later if staff ask.

## 4. The Mentor engagement card (Administrator Dashboard)

- **Card:** `render_mentor_engagement()` on `WPCPM_Administrators_Cards`, title **Mentor engagement**, anchor `wpcpm-mentor-engagement`; `mentor-engagement` joins `WPCPM_Return::ANCHORS`; `collect()` gains `mentor_engagement` (the summary only, never the rows).
- **Place on main:** after **Programs running**, before **Syncs and health**. It opens and closes as Programs running does (its badge is the `active` figure). No tile in the attention strip: it is not a queue.
- **Tiles:** the Programs running card's tile markup and classes (`wpcpm-programs__tiles`, `__tile`, `__name`, `__n`, `__l`), so the two look identical and need **no new CSS and no theme release**:

| Tile | Number | Line under it |
|---|---|---|
| Active mentors | `active` | not counting Administrators |
| Seen in the last 30 days | `seen_30` | N in the last 7 days |
| Took a program action | `acted` | N signed in without one |
| Never signed in | `never` | |
| With students now | `with_students` | N with weekly hours, N wrote notes |
| Calls held | `calls_held` | N with a note, N upcoming; N group sessions held |

- **Under the tiles:** "Administrators who also mentor are counted apart: N." and the link **Open the Mentors screen's Engagement tab**.
- **Before shipping:** the look and spacing check of Institutions module 1.93.2 (computed styles of each paired element and the gaps between consecutive blocks, compared with Programs running), per the owner's rules that dashboards look alike and that every gap of a moved block is measured.

## 5. The command, and the biweekly line

**`wp wpcredits mentor_engagement`** on `WPCPM_CLI`, which the plugin registers as `wp wpcredits`, its subcommands named like `outcome_figures` **(made exact here: the chat said `wp wpcpm mentor-engagement`; the plugin's command is `wpcredits`)**. Writes nothing.

- `--summary`: the headline figures (section 2's summary keys).
- Without it: one row per mentor, the tab's columns, by the tab's default order.
- `--view=<view>`: one of the tab's views.
- `--format=table|json|csv` (`WP_CLI\Utils\format_items()`); default `table`.
- `--now=<unix time>` for the suites only, not documented in the guide.

**The `wpcredits-biweekly-update` task** (`~/.claude/scheduled-tasks/wpcredits-biweekly-update/`) gains one step, edited only after the release is live and only after the owner sees the change:

1. Run `ssh wpcredits-dashboard 'wp wpcredits mentor_engagement --summary --format=json'` (read-only).
2. Keep the figures and the date in its `state.json` under `mentor_engagement`.
3. Add one line to the draft, counts only, never a name. For example: "Mentors: A of B active mentors used the site in the last 30 days (C more than on <the last update's date>); D of the E with students offer weekly hours."
4. Leave the line out when the command is not there or fails, and say so in the run's notes.

The task's approval gate is untouched: only the owner's explicit yes to the exact final text posts.

## 6. History seed, and the 15 Oct import

**The seed (plugin code, automatic):**

- Runs once on the first `admin_init` after the update for someone who can manage the program, guarded by the option `wpcpm_sign_ins_version`, which is written before the seed runs (as `WPCPM_Administrators_Dashboard::maybe_rename_page()` writes its own), so a seed that fails halfway never runs again on every request.
- For every account with no `wpcpm_last_sign_in`, writes the latest of: the `login` times in `session_tokens`, `_two_factor_totp_last_successful_login`, `_two_factor_email_token_timestamp` (a code sent at the password step: approximate by nature). Accounts with none of them stay empty. In batches of 200 accounts, user meta primed per batch.
- Writes `wpcpm_last_visit` for nobody: visits start at the release.
- Saves the seed's time in the option `wpcpm_sign_ins_since` (not autoloaded). The tab's note reads it.

**The import (deploy step, not plugin code):**

- A `wp eval-file` script in the deploy kit reads `import-2026-10-15.json`: one user ID and one Unix time per mentor, taken from `rows.json`'s `login_last` (the Jetpack log from 17 Jul plus the three traces). The file holds IDs and times only, no names; it stays in `session-backups/` and is never mirrored.
- Writes `wpcpm_last_sign_in` only when the account exists, holds a mentor role or record, and the stored value is absent or earlier. It never moves a date back.
- Runs after the seed. Prints how many it raised and how many it left.

## 7. Release, guide, checks, deploy

- **Version:** main is at 1.122.25. This ships as **1.122.26**, or the next free 1.122 number when the build starts. Bumped in every place the release script checks, with a changelog entry and the translation template; the mirror README's version table follows.
- **Theme:** no release, unless the section 4 check finds a gap; then the theme gets its own bump.
- **Spec and plan in the repo:** this spec goes into `docs/specs/2026-10-15-mentor-engagement-design.md` and the plan into `docs/plans/`, both with the 15 Oct reference figures taken out, because `docs/` is mirrored to the public `WordPress/WPCredits` repository. The figures stay in this vault note.
- **Program manager guide:** `docs/sections/30-admin-wpadmin.md` describes the Engagement tab (the Mentors row of the screens table and the 1.119.0 paragraph become three tabs); `docs/sections/34-admin-operations.md` describes the card. Then `php bin/build-docs.php`, and guide page 560 is republished from the build at deploy.
- **Suites:**
  - `bin/test-sign-ins.php`: `wp_login` with a code owed records nothing; `wp_login` without Two Factor, or for an account not using it, records; `two_factor_user_authenticated` records; one request records once; one visit write per day, none for AJAX, cron, CLI; the seed takes the latest of the three traces and touches no account that has a value; uninstall deletes both keys.
  - `bin/test-mentor-engagement.php`: every row and summary rule of section 2 on fixtures, including a note dated two days before a call (not noted), one dated the day before (noted), a cancellation by the student (not the mentor's), a group session a manager created on a mentor's calendar (held, not created), a staff mentor (counted apart), an inactive mentor (no follow-up view), and each view's count equal to its figure.
  - `bin/test-mentors-screen.php` gains the tab; `bin/test-administrators-dashboard.php` gains the card and its anchor.
  - The full battery and `bin/check-standards.sh` clean; the release script's check and scan (no em or en dash, no secrets, no names).
- **Deploy (only on the owner's go):** read the live version first; the release script's build, the installable zip `~/GitHub/wpcredits-program-manager.zip`, the usual kit with a server-side rollback zip; after the install: open wp-admin once so the seed runs, run the import, then `wp wpcredits mentor_engagement --summary` compared with the 15 Oct figures (`active` and `signed_in` must match the 15 Oct answer; the action and call figures may have moved since); republish page 560; purge the caches; mirror all four `WordPress/WPCredits` folders; then the biweekly task's step (section 5).
- **Privacy notice:** the one sentence of section 1 goes into the draft page 654 at deploy; it stays a draft, as it is today.

## Out of scope

- A CSV download on the tab.
- A stored history or trend line in the plugin (the biweekly task keeps the previous figures).
- Engagement views for students, institutions or sponsors (the sign-in record is there for them when wanted).
- Nudging mentors who have not signed in (an email or a Slack message) and any write-back to Airtable.
- Changing the Two Factor policy.
