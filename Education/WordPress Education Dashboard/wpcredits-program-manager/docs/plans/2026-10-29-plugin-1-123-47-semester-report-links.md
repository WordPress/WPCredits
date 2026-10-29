# Plugin 1.123.47: the semester report's links keep the institution an Administrator is viewing

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An Administrator reading an institution's semester report through the Institution Dashboard's "Viewing as institution" switcher stays on that institution: the list's **Open** link and the report's **Back to the other semesters** link keep `wpcpm_institution_view`. Today both drop it, so the page falls back to the first institution in the switcher.

**Architecture:** The screen already has the rule for its forms: `post_url()` adds `WPCPM_Institution_Roster::ARG_VIEW` for a viewer who holds `WPCPM_Roles::CAP_MANAGE`, and `leave()` and every caller outside the screen add it the same way. The two links get the same rule through one private helper. An institution member's links do not change, because their membership places them. Neither do the emails, whose callers add the view themselves (`drafted_message()`) or must not (`approved_message()`, sent to members).

**Tech Stack:** PHP, the plugin's `bin/test-*.php` suites.

**Spec:** None written. This is the finding of the 1.123.46 Docker trial (`.superpowers/session-backups/2026-10-28/plugin-1-123-46-sdd/trial-report.md`, section 3, "What the back link showed"), offered to the product owner as a follow-up and approved on 28 Oct 2026 ("Fix the semester report links").

## Global Constraints

- Use plain hyphens only, never an em dash or an en dash: in code, comments, strings, the readme and commits.
- Write US English. "Administrators" is the one name for the people who run the program. In comments, people are named by role, never by name.
- Every plugin edit bumps the version everywhere it is written (`wpcredits-program-manager.php` header and `WPCPM_VERSION`, `readme.txt` Stable tag), with a changelog entry and the translation template (`bin/make-pot.sh`).
- No new on-screen string is needed. If one turns up, it is translatable.

## Review Focus

1. **An institution member** (no `CAP_MANAGE`): the **View** link, the **Back to the other semesters** link and the mail links are exactly what they are today, with no `wpcpm_institution_view` in any of them.
2. **An Administrator on an institution with no approved report:** the **Open** link on a draft and the editor's back link both carry the view.
3. **The report-approved email** to an institution's accounts never carries the view. The review-draft email to Administrators carries it as before.
4. **The Institutions screen in wp-admin and the Administrator Dashboard's report links** are unchanged (each adds the view itself today).
5. **A record that is not a record ID** is never written into an address. The helper takes the record the page already resolved and checks it as `post_url()` would.

---

### Task 1 (plugin): the two links keep the view

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php`:
  - `render_index()`, the **Open** link, about line 382;
  - `render_editor()`, the back link, about line 748;
  - `render_reading()`, the back link, about line 564, changed only if the helper keeps it identical for a member.
- Test: `bin/test-semester-report.php` (it already draws the member's reading view, about line 3125, and the manager's index and editor).

**Interfaces:**
- Consumes:
  - `WPCPM_Institutions_Dashboard::page_url()` (string, '' when the page is not published);
  - `self::report_url( $cohort )`;
  - `WPCPM_Institution_Roster::ARG_VIEW`, `WPCPM_Roles::CAP_MANAGE`, `WPCPM_Mentors_Sync::is_record_id()`;
  - the existing `post_url( $record )` as the model of the rule.
- Produces: private `WPCPM_Semester_Report_Screen::for_viewer( $url, $record )`. It returns `$url` with `ARG_VIEW => $record` added when `$url` is not '', the current user holds `CAP_MANAGE` and `$record` is a record ID; otherwise `$url` unchanged. The name is the implementer's call if the file's idiom suggests a better one.

- [ ] **Step 1: Inventory.**
  - List every link and redirect this screen draws that leads back to the Institution Dashboard, and say for each whether it already keeps the view for a manager: `post_url()`, `render_generate_form()`, `leave()`, `bounce()`, `print_url()`, the three back or Open links, the two emails.
  - Put the list in the report.
  - Fix only the ones that drop it for a manager on this screen. Name, but do not fix, any on another module.
- [ ] **Step 2: Failing tests** in `bin/test-semester-report.php`, drawn as an Administrator viewing institution record X:
  - **(a) The list:** the **Open** link of a cohort with a report holds `wpcpm_report=<cohort>`, `wpcpm_institution_view=X` and ends `#wpcpm-report`.
  - **(b) The editor** of an approved report and of a draft: the **Back to the other semesters** link holds `wpcpm_institution_view=X`, holds no `wpcpm_report`, and ends `#wpcpm-report`.
  - **(c) As an institution member:** the **View** link and the reading view's back link hold no `wpcpm_institution_view`. They are byte-identical to today's (assert the exact href).
  - **(d) The two mail builders:**
    - `report-approved`'s `report_link` holds no view;
    - the drafted mail's `review_link` holds it once, not twice.
- [ ] **Step 3: Implement** the helper, and use it in `render_index()`'s Open link, `render_editor()`'s back link and `render_reading()`'s back link. A docblock on the helper says why, in the file's voice: a link an Administrator follows reaches `resolve_institution()`, which reads the view on that branch alone, and without it the page shows the first institution with a member.
- [ ] **Step 4:** Run `php bin/test-semester-report.php`, then every `bin/test-*.php` (each exits 0), `php bin/check-references.php` and `php bin/check-spelling.php`.
- [ ] **Step 5: Commit** "Semester report: an Administrator's Open and Back to the other semesters links keep the institution being viewed".

### Task 2 (coordinator): version, trial and release

- [ ] Write plugin 1.123.47 in every place: the changelog, then `bin/make-pot.sh`. The changelog's entry is one line for people and one "For developers" line naming the helper. Commit "1.123.47: the version, the changelog and the translation template".
- [ ] **Docker trial**, as the copy's `admin` Administrator, on the copy's TEST institution, which holds an approved report for July to December 2026:
  - open the Institution Dashboard with the switcher on that institution, open the report from the list, press **Back to the other semesters** and press **Open** again;
  - each lands on the same institution, the list shows "Approved on", and the report opens;
  - then the same as an account of that institution: its links are unchanged.
- [ ] Run the release script's check, build and scan. Mirror and deploy happen only on the product owner's yes.
