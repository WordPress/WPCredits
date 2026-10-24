# Plugin 1.123.27: the Institutions module's copy and legacy cleanup, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Everything an institution reads on the site says what is true in plain words, with no Airtable or internal vocabulary; the application form lives at /institution-application/ under a title that says it is for institutions, and /apply/ still takes a visitor there; the Administrator screens print labels instead of raw states; and the code nobody calls, the docblocks that describe phases long shipped, and the cron jobs deactivation leaves behind are gone.

**Architecture:** Copy changes in the classes that print each sentence, no new screen. The form's address moves through a one-time `init` step that renames the plugin's own page once (the shape `WPCPM_Students_Dashboard::maybe_rename_page()` has) and a `template_redirect` handler that answers a 404 at the old address with a permanent redirect, since WordPress keeps no old slug for a page. One small new class, `WPCPM_Dates`, gives the Institution Dashboard one way to print a day and a moment.

**Tech Stack:** WordPress 7.1 plugin PHP (7.4 and 8.x), the plugin's `bin/test-*.php` suites (plain PHP with their own stand-ins, one process each), `bin/check-references.php`, `bin/check-spelling.php`, `bin/check-standards.sh` (phpcs, WordPress standard), `bin/check-dead-annotations.php`, `bin/build-docs.php`, the Docker copy of the live site for the coordinator's trial.

**Spec:** the review of the Institutions module of 22 October 2026, section B ("Copy and legacy cleanup (one release)"): `.superpowers/session-backups/2026-10-22/institutions-audit/findings.md` in this repository (local, not mirrored). Section A shipped in 1.123.26; sections C, D and E are not this release. The site's owner said go on 24 October 2026, and decided the form's address on 22 October 2026: the institution application form moves from /apply/ to /institution-application/, /apply/ redirects to it, and its title says it is for institutions ("Bring WordPress Credits to your institution") with one intro line.

**Repository:** /Users/maciejpilarski/GitHub/wpcredits-program-manager, worktree `.worktrees/plugin-1-123-27`, branch `release/1.123.27-institutions-cleanup`, from `main` at `a06d23eb` (plugin 1.123.26, live) with this plan committed on top. Every anchor below is that tree's text; line numbers are a guide, the quoted text is the anchor.

**How to read the wording.** Every user-facing sentence this plan gives is copied verbatim. A sentence or a choice marked **[R]** is this plan's proposal: the controller rules on it (or asks the owner) before the task starts, and the implementer copies whatever the ruling says. Anything unmarked is the owner's words, the finding's, or a fact of the code.

## Scope and rulings

In: every bullet of section B that is a copy fix, a label, dead code with no reader, a stale docblock or the deactivation fix, in seven tasks and the coordinator's eighth. The rulings this plan makes:

- **R1. The page is the plugin's, so the plugin moves it.** `WPCPM_Institution_Application::ensure_page()` creates the page and records it in `wpcpm_application_page_id`; on live that is page 751, slug `apply`, title "Apply to the WordPress Credits Program", content the bare block. A one-time `maybe_move_page()` on `init` (priority 20, after `register`) changes each of the three only while it is still the plugin's own: the slug while it is `apply`, the title while it is the old title, the content while it is exactly the bare block. A field an Administrator changed by hand is left alone. A flag option stamped before the work, as `maybe_rename_page()` stamps its own, makes it run once.
- **R2. The redirect is the plugin's own, on `template_redirect` at priority 9.** `wp_check_for_changed_slugs()` keeps no `_wp_old_slug` for a hierarchical post type and `wp_old_slug_redirect()` reads only the `name` query variable, so neither serves a page. The handler answers only a 404 whose request names exactly the old slug (`pagename`, or `name` where a missing page falls through to the post rule under /%postname%/ links), sends a 301 to `page_url()` with the request's query string kept, and runs before core's `redirect_canonical()` and `wp_old_slug_redirect()` (both priority 10) so core's guess cannot send the visitor somewhere else. A real page published at /apply/ later is not a 404, so it wins. Permanent, because the owner decided the address and search engines should learn it. Cost if wrong: a browser that saw the 301 keeps going to the new address after a change of mind.
- **R3. The intro line is page content, not block output.** It is a paragraph before the block in the page, written by `ensure_page()` on a new site and by the one-time move on live, so an Administrator edits it in the editor like the title. The block's output does not change.
- **R4. Removing a placeholder from an email is a silent break, so none is removed.** An Administrator's saved edit that uses a placeholder the email no longer lists is set aside (`WPCPM_Mail_Templates::state()`, "Edit set aside: it uses ..."). So `{institution_record}` stays in the lists of `member-last` and `agreement-template` and leaves only the shipped bodies; every placeholder this release adds is optional. On the Docker copy of 21 October 2026 every institution and report email is in its shipped state.
- **R5. Statuses stay raw where they are read, plain where they are printed.** The roster's groups, its search (`matches()`), the CSV export and every count keep reading Airtable's own words; only the words a school reads change.
- **R6. The `final` vocabulary goes, the drafting day's stamp stays.** `WPCPM_Semester_Report::maybe_upgrade()` flips `final` reports to `approved` and also stamps `wpcpm_report_autodraft_since`, the day before which the job drafts nothing, on a site that never had it; without that stamp a fresh site's first run would draft every past semester. The flip, `OPT_STATE_VERSION`, `STATE_VERSION` and the `init` hook go; the stamp moves into `WPCPM_Institutions::schedule_cron()`'s branch that puts the drafting job on the clock, so it is asked only when the job is scheduled (a new site, or a reactivation after R8 took the job off), never on an ordinary request, and as an `add_option()` it keeps a day already stored (live has it). A site jumping from before 1.91.0 straight to this release would keep its `final` reports reading as drafts; live and the Docker copy flipped theirs in 1.91.0 (the coordinator reads `wpcpm_report_state_version` on both before deploying). Cost if wrong: a forgotten pre-1.91.0 site.
- **R7. The Doc check is removed, not built.** About 450 lines of `WPCPM_Agreement_Template` (`drift()` and its helpers) have had no caller since they shipped, and the Settings row and the guide promise a "Check against the Doc" button that no screen draws. They go, the promise goes, and `agreement_doc_url` stays, since the Agreement template card links the Doc from it. The suite's check that the plugin's copy carries the Doc's load-bearing sentences (`bin/fixtures/agreement-doc-en.txt`) and the checksum pin stay. If the owner wants the check later, it is in history at `a06d23eb`.
- **R8. Deactivation clears the six daily jobs, from one list.** `WPCPM_Institutions::deactivate()` clears the jobs `schedule_cron()` schedules, read from one list both use, as the Sponsors module clears its own. The single events (a semester report's ask queue, an import's next slice) are left: WordPress drops a due event that has no listener, and a reactivation inside their minutes picks them up. Uninstall, which today calls `wp_clear_scheduled_hook()` with no arguments for `CRON_ASK` and so clears none of the events scheduled with a report's ID, unschedules `CRON_ASK` and the import's `CRON_TICK` whatever their arguments (`wp_unschedule_hook()`). **[R]** (the uninstall half is outside the finding, on the same lines and a real defect).
- **R9. Labels change, Airtable column names never.** The internship question's column `How do your internships or practices typically work?` and the sponsor form's `Company Name`, `Contact Person Full Name` and `Contact Email` are the keys records are written under and the source of the form's field names (`form_key()`); only the `label` beside each changes.
- **R10. The Slack label that is wrong is the student one. [R]** The channel every guide entry links, C0959D2M3T8, is #wpcredits, the program's own channel, which the mentor decline email calls "the WordPress Credits Slack channel" and the sponsor guide "the program's Slack channel"; the student entry alone calls it "the students Slack channel", and the institution entry's comment says it is a different channel from the students' when it is the same one. So the student label and the student guide's sentence change, and the comment is corrected. If the owner says the channel is the students' own, only the comment changes.
- **R11. The read line is printed once, at the foot of the Institution Dashboard** (the owner's 1.96.5 place for it); the cohort strip stops printing its copy. **[R]**
- **R12. The mentor and sponsor acknowledgements keep "form at %2$s" for now.** The site's name inside a sentence gets its "the" in the institution emails, the finding's scope; the same sentence in `includes/mail/templates/mentor-applications.php` and `sponsors.php` is the controller's to add or leave. **[R]**

### The controller's rulings on the [R] proposals (24 October 2026)

Every sentence or choice marked [R] in this plan is accepted as written, with these exceptions, which the implementer copies instead:

- Task 2, `status_words()`: `Not moving forward` prints "Not continuing", not "Did not start", since a student may have started before stopping.
- R12: the mentor and sponsor acknowledgements keep "form at %2$s" in this release. Administrators have saved their own edits of the mentor emails, and a change to the shipped text would mark those edits as changed since.
- Task 1's intro line, R10's Slack label and R11's read line stand as proposed; the owner can change the intro line in the editor at any time, since it is page content (R3).

### Not in this release

- The roster's "No start date" marker linked to the edit form: `WPCPM_Institution_Student_Form::render_form()` is drawn nowhere since the Phase 4 cards left the page (section C1), so a link would open nothing; Task 7 corrects the docblock that says otherwise.
- Splitting the Institutions screen's "Sync and storage" tab: a design, not a copy fix.
- One name for the people of an institution across screens (members, representatives, accounts): the owner's decision of one word, then a pass of its own.
- The handbook's Get Involved page and the WPCredits mirror's README and decks, which link the Airtable interest form: not in this repository; the owner edits the handbook, the coordinator the mirror, after the deploy and on the owner's answer (Task 8).
- The theme's `wpcredits_institution_page_url()` and `.wpcpm-dashboard__switcher-none`: theme code, for the theme's next release.

## Global Constraints

- Do not touch the version, `readme.txt`'s changelog or the `.pot`: the coordinator does those after you.
- Never use em or en dashes anywhere.
- US English.
- "Administrators" is the one name for the program's staff.
- Full product names: "Student Report Card", "Mentor Report Card", "Institution Dashboard", "Administrator Dashboard".
- No person's name in code, comments, docs, tests or commit messages: roles only.
- Test addresses use `example.test`; invented record IDs are uppercase letters and digits.
- Match the surrounding code: docblocks that explain why, naming, escaping, nonces and policy checks.
- phpcs: no new errors or warnings against main's count, recorded before Task 1.
- Tests first in the repository's style (`bin/test-*.php`); record each task's red and green.
- Update the guides that describe changed behavior (`docs/sections/`), then `php bin/build-docs.php`.
- Comments state the code's fact and name no plan, task, review or finding.
- Before reporting, run from the worktree and quote the last lines: `TMPDIR=$(mktemp -d) php bin/check-temp-litter.php`; `php bin/check-references.php`; `php bin/check-spelling.php`; `php bin/test-fixtures.php`; `bash bin/check-standards.sh` and `php bin/check-dead-annotations.php`, all with `PATH="/private/tmp/claude-501/-Users-maciejpilarski-GitHub/25b9f120-1b7e-4cf2-9739-9a4891fc924e/scratchpad/phpcs-1g:$PATH"`.
- Do not dispatch subagents, reviewers included. No `ssh`, `docker`, `gh`, `git push`, network calls, deploys or installs. Never print secrets. Never `git stash`.
- Commits: one per task, each a subject line plus one plain paragraph, ending with the repository's Co-Authored-By trailer, written with `git commit -F <file>`.

## Review Focus

1. **The old address wherever it was already printed:** acknowledgements and invitations already sent, bookmarks, slides, the handbook, and any Site Editor template or synced pattern that saved the address `wpcredits_hub_url( 'institution_application' )` answered when it was inserted (the theme's `hub-doors` and `hub-cta` patterns print it at insert time). The redirect answers exactly /apply/ and nothing else, keeps the query string (a visitor landing mid-redirect at deploy time carries `wpcpm_app` or `wpcpm_app_said`), wins over core's canonical guess, and steps aside once a real page sits there; the one-time move never renames a page an Administrator renamed, and never takes a slug another post holds (Task 1).
2. **The form still posts what Airtable takes:** the internship label changes while its column, and so its posted field name, stays byte for byte; the country combobox posts the Countries record ID, as the select did, and not the country's name, as the mentor form's does; the hidden select carries no `required`, or a browser refuses the form over a control nobody can see, while the server still refuses a form with no country (Task 6).
3. **An Administrator's saved email edit:** a changed shipped text shows "Edited, and the shipped text has changed since", which is right, but a placeholder taken out of a list, or made required, sets the edit aside without anyone noticing (R4); and the three report senders must pass `site_name`, or their new subjects read "[] ..." (Task 5).
4. **Dead code with a reader nobody remembers:** `maybe_upgrade()`'s stamp of the drafting day (R6), the print handler's write of `_wpcpm_report_exported`, the import form's call into the check log, rows of `wpcpm_import_log` and `wpcpm_agreement_drift` left in live's options table after their code goes, and any CLI command, the release script, the theme or the mirror's tooling calling a removed name (Task 7 greps each one across `~/GitHub` and `~/Projects/wpcredits-release`).
5. **Display changes that must stay display:** a stored `Y-m-d` turned into a time with `strtotime()` and printed with `wp_date()` shows the day before on a site west of UTC, which `WPCPM_Dates::day()` must not do (Task 3); and the roster's plain status words, the Stage line hidden from members and the people card's new mark change nothing that is filtered, counted, exported or decided (R5, Task 2).

## Execution

Strictly sequential in one worktree: Tasks 1 and 6 share `class-wpcpm-institution-application.php`, Tasks 1, 4 and 7 share `class-wpcpm-institutions.php`, Tasks 2 and 3 share the people card and the roster view. The most capable reviewer goes to Task 1 (the redirect and the move) and then Task 7 (the removals).

The battery, before Task 1 for the record and after every task:

```bash
n=0; bad=0; ok=0
for f in bin/test-*.php; do
	n=$((n+1))
	out=$(php "$f" 2>&1) || { bad=$((bad+1)); echo "FAIL $f"; }
	ok=$((ok + $(printf '%s\n' "$out" | grep -c '^ok ')))
done
echo "battery: $n suites, $bad failing, $ok ok lines"
php bin/check-references.php | tail -n 1
```

---

### Task 1: The form's address and title

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-application.php` (`SLUG` and its docblock, new constants, `init()`, `ensure_page()`, new `page_title()`, `page_content()`, `maybe_move_page()`, `redirect_old_address()`)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`uninstall()` deletes the new flag beside `OPT_PAGE`)
- Modify: `docs/sections/31-admin-settings.md` ("Applications from institutions"), `docs/sections/34-admin-operations.md` ("Pages the plugin owns", and a row in "Where the plugin keeps its data")
- Test: `bin/test-institution-application.php` (its `get_permalink()` stand-in answers the new address; stand-ins for `is_404()`, `get_query_var()`, `wp_update_post()`, `get_page_by_path()` by slug, and a redirect that records instead of exiting, in the style the suite stubs `wp_safe_redirect()` today), `bin/test-institutions-screen.php` (uninstall list)

**Interfaces:**
- Produces: `WPCPM_Institution_Application::SLUG = 'institution-application'`, `OLD_SLUG = 'apply'`, `OLD_TITLE = 'Apply to the WordPress Credits Program'` (the literal live holds, compared untranslated), `OPT_MOVED = 'wpcpm_application_page_moved'`, `MOVE_VERSION = 1`.
- Produces: `page_title(): string` and `page_content(): string` (the paragraph and the block, each in its block comments), used by `ensure_page()` and `maybe_move_page()`; `maybe_move_page(): void` on `init` at 20; `redirect_old_address(): void` on `template_redirect` at 9. Task 8 consumes the constants.

- [ ] Step 1: tests. `ensure_page()` on an empty site creates a published page at `institution-application` titled with `page_title()` and holding `page_content()`, ungated as before; it adopts an existing page at the new slug, and at the old slug when the option is missing (a restored site), rather than creating a second. `maybe_move_page()` on a site whose recorded page has slug `apply`, the old title and the bare block moves all three in one `wp_update_post()` and stamps `OPT_MOVED`; with the title or the content edited by hand it moves only what is still the plugin's; with another post already at `institution-application` it leaves the slug and still stamps; a second run does nothing; `page_content()` passes `wp_kses_post()` unchanged (the move can run on a visitor's request, where kses is on). `redirect_old_address()`: a 404 with `pagename` `apply`, and one with `name` `apply`, each send a 301 to `page_url()`; `/apply/?wpcpm_app_said=closed` keeps its query string; not a 404, any other path, `apply-now`, or no published page send nothing; it is hooked on `template_redirect` at 9 and `maybe_move_page()` on `init` at 20. The Institutions module's uninstall deletes `OPT_MOVED`.
- [ ] Step 2: run red: `php bin/test-institution-application.php`, `php bin/test-institutions-screen.php`.
- [ ] Step 3: implement. The new strings, verbatim:
  - The page's title (the owner's words): "Bring WordPress Credits to your institution"
  - The intro line, a paragraph before the block **[R]**: "For universities, colleges and schools that would like to offer WordPress Credits to their students: an Administrator reads every application and writes back within a few working days."
  - `SLUG`'s docblock says the address was renamed once, from `apply`, on the owner's decision of 22 October 2026, so the address names the institutions it is for as `sponsor-application` names the sponsors, and that the old one answers through `redirect_old_address()`. `ensure_page()`'s comment about `apply-2` names the new slug.
- [ ] Step 4: green; the battery and the checks.
- [ ] Step 5: guides. 31-admin-settings.md, in "Applications from institutions", after "its address is under the switch": "Since 1.123.27 the form is at /institution-application/, titled "Bring WordPress Credits to your institution", and /apply/, its address before, takes a visitor there." 34-admin-operations.md, "Pages the plugin owns", the same fact in one sentence; the names table gains `wpcpm_application_page_moved` | `WPCPM_Institution_Application::OPT_MOVED` (the one-time move of the application form's page to its new address, 1.123.27). Then `php bin/build-docs.php`.
- [ ] Step 6: commit, subject "The institution application form moves to /institution-application/ under a title for institutions".

### Task 2: No Airtable or internal words in front of a school

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`leads()`, `render_identity()` and its caller)
- Modify: `includes/modules/class-wpcpm-institution-roster-view.php` (`group_note()`, the mentor cell's sentence, `program_cell()` and a new private `status_words()`, `render_unlinked()`)
- Modify: `includes/modules/class-wpcpm-institution-student-view.php` (the no-record sentence)
- Modify: `includes/modules/class-wpcpm-institution-students.php` (`paused_sentence()`, `MIRROR_VIEW`)
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render_member()`, `render_contact_row()`, `confirm_text()`)
- Test: `bin/test-institutions-dashboard.php`, `bin/test-institution-roster-view.php`, `bin/test-institution-student-view.php`, `bin/test-institution-graduate.php`, `bin/test-institution-people.php`, and every suite the grep of Step 2 finds pinning an old sentence

- [ ] Step 1: tests. A member's Institution Dashboard, settled and locked, holds none of "Students table", "report record", "Stage:", "viw" or "the program's contact", no "Airtable" but the people card's read line (Task 3 rewrites it, and its test then holds the page to no "Airtable" at all), and the Semester report module's lead below; an Administrator through the switcher still sees the Stage line; a row with no program and the pipeline status `Not moving forward` prints the plain word, one with the status `In Sensei` prints the compiled track's name, and the group it is filed in, the search and the cohort counts are those of the raw status (R5); the people card's mark and tooltips, the Remove confirm's note for the contact's row, the paused refusal (no view ID in it) and the no-record sentence are the words below.
- [ ] Step 2: run red; then `grep -rn` each old sentence below across `bin/` and fix the suites that pin it.
- [ ] Step 3: implement, verbatim:
  - Semester report lead (`leads()`, `MODULE_REPORT`): "The report the program prepares for your institution each semester, to read and download as a PDF once an Administrator has approved it."
  - The identity header's "Stage: %s" line is drawn only for a viewer who can manage the program: `render_identity( array $profile, $can_manage )`, the caller passing what it already holds. No new words.
  - Group notes (`group_note()`) **[R]**: current "On the program now, with a mentor assigned or a progress record already started."; waiting "Signed up, with no mentor assigned and no progress record yet."
  - Mentor cell, the case with no report record **[R]**: "A mentor is assigned. Their name shows here once the student's progress record reaches this page." (the other case, "A mentor is assigned. Their name has not reached this page yet.", stays).
  - The fifth list (`render_unlinked()`) **[R]**: heading "Enrollment not recorded yet"; note "These students are reporting their progress, but the program records hold no enrollment for them yet, so they are not counted in the groups above. An Administrator needs to complete the record."
  - Student card (`class-wpcpm-institution-student-view.php`, "This student has no report record ...") **[R]**: "This student has no progress record yet, so there is no Student Report Card to show."
  - `program_cell()` prints a pipeline status through `status_words()`: the compiled track's name where `WPCPM_Program::label()` has one, otherwise **[R]** `Interested` "Signed up", `In Sensei Self-onboarding` "Getting started", `Not moving forward` "Did not start", `Fail` "Did not complete", `Graduate` "Graduated", anything else as it is. Only the printed words change (R5).
  - Paused refusal (`paused_sentence()`) **[R]**: "The program records say this student is paused, so this page cannot record a change for them: they would be emailed while the rest of their records went on saying paused. Ask your contact at the program to take them off pause first." `MIRROR_VIEW` goes with its last reader; the class docblock keeps the view's ID for the Administrator who has to find it in the base.
  - People card mark, both rows **[R]**: "the institution's contact". Tooltip on a member's row: "The program writes to this address about the institution." On the row with no account: "The program writes to this person about the institution."
  - Remove confirm's note on the contact's row **[R]**: "The program will go on writing to this address as the institution's contact until an Administrator changes it."
  - Docblocks of the changed methods say what the words are for, without "Airtable" where the reader is a school.
- [ ] Step 4: green; the battery and the checks.
- [ ] Step 5: no guide describes these sentences (there is no Institution guide in this repository, section C2); nothing to rebuild unless a suite's grep found a guide line.
- [ ] Step 6: commit, subject "The Institution Dashboard in plain words: no Airtable names, raw statuses or view IDs in front of a school".

### Task 3: One date format, one read line, the right Slack label

**Files:**
- Create: `includes/class-wpcpm-dates.php`; required in `wpcredits-program-manager.php` beside `class-wpcpm-cohort.php`
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render_read_line()`), `includes/modules/class-wpcpm-institution-panel.php` (`render_read_line()`, the three `post_date()` sentences, the agreement card's accepted date), `includes/modules/class-wpcpm-institution-invite.php` (the pending invitation's line), `includes/modules/class-wpcpm-institution-roster-view.php` (`render_strip()`, `read_line()` and its docblock)
- Modify: `includes/tools/class-wpcpm-handbook-assistant.php` (`guides()`: the student entry's `chat`, the institution entry's comment), `docs/sections/10-student-card.md`
- Test: `bin/test-dates.php` (create), `bin/test-institution-people.php`, `bin/test-institution-panel.php`, `bin/test-institution-invite.php`, `bin/test-institution-roster-view.php`, `bin/test-institutions-dashboard.php`, `bin/test-handbook.php`

**Interfaces:**
- Produces: `WPCPM_Dates::day( $value ): string`: a `Y-m-d` string, or a longer one whose first ten characters are one (a `post_date`), is the day it names in `get_option( 'date_format' )`, read as `WPCPM_Mentors_Dashboard::format_dates()` reads one so that no time zone moves it; an integer is a Unix time shown on the site's clock with `wp_date()`; empty or unreadable gives ''. `WPCPM_Dates::moment( $time ): string`: `wp_date()` in `date_format` and `time_format` joined by a space, as the roster's read line prints today; 0 gives ''.

- [ ] Step 1: tests. `bin/test-dates.php`: `2026-09-03` under a site time zone of `America/Los_Angeles` and of `Pacific/Auckland` reads September 3 in the site's format; a Unix time shows on the site's clock; a post date string reads its day; '' and `not-a-date` give ''. The people card's read line, the agreement panel's read line, the panel's generated, received and returned-note dates, the agreement card's "Accepted on" date and the pending invitation's "Invited by ... on ..., good until ..." line each print the site's formats, never `Y-m-d`; a member's Institution Dashboard holds "Read from the program records on" exactly once, at its foot; the student guide entry's `chat` is the new label; and a member's Institution Dashboard, settled and locked, now holds no "Airtable" at all.
- [ ] Step 2: run red.
- [ ] Step 3: implement, verbatim:
  - People card read line: "The institution's details have not been read from the program records yet. The people above are as they are right now." and "The institution's details were read on %1$s (%2$s ago). The people above are as they are right now." (`%1$s` from `moment()`).
  - Agreement panel read line, the dated one **[R]**: "The agreement's state was read from the program records on %1$s (%2$s ago)." (the undated one stays).
  - Every other sentence keeps its words; only the dates inside it go through `day()` or `moment()`.
  - The read line (R11): `render_strip()` no longer prints it; the roster's own footer prints it where the module is not titled, and the dashboard's foot where it is, so a page holds it once. `read_line()`'s docblock says so.
  - Slack (R10) **[R]**: the student entry's `chat` becomes "Ask in the WordPress Credits Slack channel"; the institution entry's comment says the program's own channel, which the students' entry links too; 10-student-card.md line 117 reads "the WordPress Credits Slack channel".
- [ ] Step 4: green; the battery and the checks.
- [ ] Step 5: `php bin/build-docs.php`.
- [ ] Step 6: commit, subject "The Institution Dashboard prints every date in the site's format, its read line once, and the Slack channel by its name".

### Task 4: Administrator words, on the Administrator Dashboard and the Institutions screen

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_programs()`, `render_application()`, `render_requests()`, the strip's `requests` tile)
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (new public `summary_label()`), `includes/modules/class-wpcpm-semester-report.php` (new public `state_label()`)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`application_state_label()` public, `describe_summary()` reads `summary_label()`, `description()`, `render_sync_panel()`, `render_template()`'s checksum row, `render_semester_reports()`'s count and its Approved/Draft words)
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php` (its Approved/Draft ternary reads `state_label()`)
- Modify: `docs/sections/34-admin-operations.md`, `docs/sections/30-admin-wpadmin.md`
- Test: `bin/test-administrators-dashboard.php`, `bin/test-overview.php`, `bin/test-institutions-screen.php`, `bin/test-institution-agreement.php`, `bin/test-semester-report.php`

**Interfaces:**
- Produces: `WPCPM_Institution_Agreement::summary_label( $state ): string`, the words `describe_summary()` holds today ("Not started", "Template generated", "Awaiting review", "Returned", "Revoked", "Accepted", "On file"), an unknown state as it is. `WPCPM_Semester_Report::state_label( $state ): string`: `none` **[R]** "Not drafted", `draft` "Draft", `approved` "Approved". `WPCPM_Institutions::application_state_label( $state )` becomes public, its words unchanged.

- [ ] Step 1: tests. Programs running's Agreement and Report cells print the labels above, never `none`, `on file`, `on_file`, `accepted` or `draft`; an Institution applications row prints the state as `application_state_label()` words it, its title links to `WPCPM_Institution_Application::queue_url()` as a sponsor row's links to its own, and a held row carries the note below; the requests card and its tile carry the new names; the Institutions screen's description, sync card and checksum row hold none of "Subscriber role", "public columns" or "sha256"; the Semester reports heading's count is the drafts waiting, the number the Administrator Dashboard's card counts.
- [ ] Step 2: run red.
- [ ] Step 3: implement, verbatim:
  - The application's state is printed as the sponsor card prints its own, the words as given, with no `ucfirst()` (which breaks a translation that begins with a multibyte letter).
  - Held note: "Held by the anti-spam checks. Open it on the Institutions screen to read which."
  - Card title and screen line **[R]**: "Requests from institutions"; the tile "Requests from institutions open, %s", with its translators comment's example "2 Requests from institutions open, 1 overdue" and the `_nx()` context "of the open requests from institutions".
  - Module description **[R]**: "Educational institution records from Airtable, the students each one has in the program, and the state of every Collaboration Agreement. Each institution has an Institution Dashboard, its own people and its students; their accounts are created and invited on the Accounts tab."
  - Sync card **[R]**: "Reads the Countries table, then each institution's name, stage, country, city, website and contact, and its eight agreement columns, and rebuilds the institution list and each institution's agreement state. The long written answers in the base are never read."
  - Checksum row's note **[R]**: "(the start of the fingerprint the plugin's tests check, which changes only when a word of the agreement does)"
  - Semester reports heading: the count is `count( $drafts )`; no new words.
- [ ] Step 4: green; the battery and the checks.
- [ ] Step 5: guides. 34-admin-operations.md: *Mentor requests* becomes *Requests from institutions* (lines 179 and 186) and the strip's tile list says it; 30-admin-wpadmin.md line 48 "mentor requests" becomes "requests from institutions". `php bin/build-docs.php`.
- [ ] Step 6: commit, subject "The Administrator Dashboard and the Institutions screen print labels, not stored states, and call institutions' requests by their name".

### Task 5: Email copy

**Files:**
- Modify: `includes/mail/templates/institutions.php`, `includes/mail/templates/reports.php`, `includes/mail/class-wpcpm-mail-catalog.php`
- Modify: `includes/modules/class-wpcpm-agreement-generate.php` (the template notice's values), `includes/modules/class-wpcpm-institution-agreement.php` (the Administrators' `uploaded_by` fallback), `includes/modules/class-wpcpm-semester-report-screen.php` (`drafted_message()`, `approved_message()`, `ask_message()` pass `site_name`), `includes/modules/class-wpcpm-institution-panel.php` (the `agreement-revoked` outcome), `includes/modules/class-wpcpm-institutions-dashboard.php` (`render_banner()`)
- Test: `bin/test-emails-templates.php`, `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-semester-report.php`, `bin/test-agreement-generate.php`, `bin/test-institution-members.php`, `bin/test-institution-agreement.php`, `bin/test-institution-panel.php`, `bin/test-institutions-dashboard.php`

- [ ] Step 1: tests. Each changed body and subject renders the words below from its examples; no institution or report email's shipped text holds "agreement panel", "Airtable" or an `{institution_record}`; `member-last` and `agreement-template` still list `institution_record`, so a saved edit that uses it still fits (R4); every placeholder added is optional; each report sender passes `site_name` and its subject renders with the site's name in brackets; the catalog names are the ones below.
- [ ] Step 2: run red.
- [ ] Step 3: implement, verbatim:
  - Acknowledgement, "What happens next" **[R]**: "What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account for it on the site, where you generate the program's Collaboration Agreement or upload your own, then sign it and upload the signed copy."
  - Acknowledgement, last paragraph: "This message went to %1$s because that address was given on the application form at the %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed." (translators: "2: the site's name, read after "the", as in "the Education Directory".")
  - Invitation, named: "%1$s has invited you to see %2$s's students in the WordPress Credits Program on the %3$s." No sender: "You have been invited to see %1$s's students in the WordPress Credits Program on the %2$s." (translators comments as above).
  - Revoked: "Until an agreement is in force again, your Institution Dashboard shows only the Collaboration Agreement card. Nothing about your students has been deleted."
  - Received: "If it was the wrong file, withdraw it from the Collaboration Agreement card on your Institution Dashboard and upload the right one."
  - Returned: "Upload the corrected copy from the Collaboration Agreement card on your Institution Dashboard. Replying to this message reaches the Administrator who wrote the note."
  - Template notice: "The reason given was: %1$s (template language %2$s, institution %3$s, name asked for "%4$s")." with `%3$s` now `{institution_name}`, a new optional placeholder of the email the generator fills from the pipeline index (the record when the index holds no name). Its last paragraph **[R]**: "The template is part of the plugin, so a developer fixes it. A bracketed word other than the institution's name is refused on purpose: it would otherwise be printed on a document somebody signs. Nothing can be generated in this language until it is fixed."
  - Last member: "%1$s is no longer a member of %2$s as of %4$s (%3$s). No account can act for this institution on the site now." (translators "1: person, 2: institution name, 3: why, as removed, left or revoked, 4: date.", arguments `{member_name}`, `{institution_name}`, `{ended_because}`, `{left_on}`), and "The institution's record, its agreement, its students and its history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there."
  - The Administrators' upload notices name an uploader with no name "a member of the institution", not the record ID; `uploaded_by`'s explanation: "Who uploaded it: their name, or, when their account holds none, "a member of your institution" in the institution's copy and "a member of the institution" in the Administrators'." `institution_name`'s explanation, in both areas: "The institution's name, or its record ID when the site holds no name for it."
  - Report subjects: "[%1$s] Semester report drafted: %2$s", "[%s] Your semester report is ready", "[%s] May your institution mention you in its semester report?", each with an optional `site_name` (explanation "The site's name.").
  - The screens' "agreement panel": the `agreement-revoked` outcome "The agreement is revoked. From its next page load the institution's account shows only the Collaboration Agreement card, and everybody there has been emailed your note."; the banner "This institution's agreement is not settled: %s. Members see only the Collaboration Agreement card."
  - Tools > Emails names **[R]**: `institution-applied` "Institution application received, to the applicant"; `institution-applied-no-call` "Institution application received, to an applicant with no call to book"; `institution-information` "Question about an institution application"; `institution-declined` "Institution application declined"; `agreement-landed` "Collaboration Agreement uploaded by an institution, to Administrators"; `agreement-landed-flagged` "Collaboration Agreement uploaded by an institution with the scan's notes, to Administrators"; `agreement-reminder` "Collaboration Agreements from institutions waiting for review, to Administrators"; `agreement-template` "Collaboration Agreement for an institution could not be generated, to Administrators"; `report-drafted` "Semester report drafted, to Administrators"; `report-approved` "Semester report approved, to the institution"; `report-consent` "Consent for the semester report, to the student". The ids do not change, so the Log's rows keep their names.
- [ ] Step 4: green; the battery and the checks.
- [ ] Step 5: no guide lists these emails' words; nothing to rebuild unless a suite's grep found a guide line.
- [ ] Step 6: commit, subject "Institution and report emails: true about the agreement, the site called by its name, no Airtable record IDs, a subject prefix on every one".

### Task 6: Form copy

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-application.php` (`fields()`'s internship label, `render_field()`'s `choices` and `country` branches)
- Modify: `includes/modules/class-wpcpm-sponsor-application.php` (`fields()`'s three labels)
- Modify: `assets/css/application.css` only if the label's Required mark needs a rule the mentor form's markup already has
- Test: `bin/test-institution-application.php` (requires `includes/class-wpcpm-dashboards.php`, as `bin/test-mentor-application-form.php` does), `bin/test-sponsor-application.php`

- [ ] Step 1: tests. The internship question's label reads "practicums" while `fields()`'s key and its `form_key()` are unchanged; its label carries an id and the "Required" mark, and its choices sit in a `role="group"` labelled by it and described by its hint (the mentor form's pattern of 1.122.31); the country is `WPCPM_Dashboards::render_combo()`'s field, its select's options valued by Countries record ID, A to Z, with the label's Required mark and no `required` attribute, and a post with no country is still refused with the same problem it gets today; the sponsor form's three labels are the new ones and `fields()`'s keys are the column names they were (R9).
- [ ] Step 2: run red.
- [ ] Step 3: implement, verbatim:
  - Internship label: "How do your internships or practicums typically work?" (the key stays `How do your internships or practices typically work?`).
  - Country field: the strings the mentor form passes `render_combo()` ("Choose a country", "Type to find your country", "No country has that name.", "Countries in the list: %s"), the same translatable strings, so nothing new is translated.
  - Sponsor labels: `Company Name` "Name of your company"; `Contact Person Full Name` "Name of the person we should contact"; `Contact Email` "Their email address" (the institution form's own two).
- [ ] Step 4: green; the battery and the checks.
- [ ] Step 5: no guide quotes these labels.
- [ ] Step 6: commit, subject "Application forms: plain labels, a marked required question, and one field to type a country into".

### Task 7: Dead code, stale docblocks, the Doc check that never shipped, and deactivation

**Files:**
- Modify: `includes/class-wpcpm-agreement-template.php` (R7: `OPT_DRIFT`, every `DRIFT_*` constant, `drift()`, `cached()`, `report()`, `unavailable()`, `remember()`, `forget_drift()`, `export_url()`, `fetch_doc()`, `normalise()`, `strip_bom()`, and the class docblock's paragraph about them)
- Modify: `includes/class-wpcpm-settings-screen.php` (the agreement wording row's description), `includes/class-wpcpm-settings.php` (the comment at `agreement_doc_url`)
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`rebuild_all()`; the docblocks of `STAGE_ORDER`, `TERMINAL_STAGES` and `was_recorded_in_airtable()`)
- Modify: `includes/modules/class-wpcpm-institutions-index.php` (`stage_counts()`)
- Modify: `includes/modules/class-wpcpm-institution-import.php` (`staged_for()` and `active_for()`'s docblock; `log_check()`, `log()`, `suspicious()`, `OPT_LOG`, `LOG_MAX`; `delete_all()` keeps deleting `wpcpm_import_log` by name), `includes/modules/class-wpcpm-institution-import-form.php` (the call to `log_check()`), `includes/modules/class-wpcpm-institution-create.php` (`META_REASON` and its write in `park()`; the audit row keeps the reason)
- Modify: `includes/modules/class-wpcpm-institution-invite.php` (`invites_for()`, `META_ACCEPTED` and its write)
- Modify: `includes/modules/class-wpcpm-semester-report.php` (`exported_at()`, `mark_exported()`, `META_EXPORTED`; R6: the `final` flip, `OPT_STATE_VERSION`, `STATE_VERSION`, the `init` hook, and the docblocks at `META_STATE`, `META_APPROVED` and `state()`; `delete_all()` keeps deleting `wpcpm_report_state_version` by name), `includes/modules/class-wpcpm-semester-report-screen.php` (the print handler's write and its comment)
- Modify: `includes/modules/class-wpcpm-institutions.php` (a private `daily_jobs()` list read by `schedule_cron()` and `deactivate()`; `schedule_cron()` stamps `WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE` with `add_option()` in the branch that schedules `CRON_AUTODRAFT`, and nowhere else; `uninstall()` deletes `wpcpm_agreement_drift` by name and unschedules `CRON_ASK` and the import's `CRON_TICK` with `wp_unschedule_hook()` (R8); the docblocks at the class head ("Phase 1 is manager-only") and `FILTER_GAP`, and `render_template()`'s "No drift button in this phase")
- Modify: `includes/modules/class-wpcpm-institution-policy.php` (`ACT_VIEW_SEMESTER_REPORT` and `ACT_EDIT_SEMESTER_REPORT` docblocks), `includes/modules/class-wpcpm-institutions-dashboard.php` (`card()`'s docblock), `includes/modules/class-wpcpm-institution-roster-view.php` (`dates_cell()`'s docblock)
- Modify: `assets/css/institution.css` (`.wpcpm-agreement-panel__soon`, `.wpcpm-agreement-card__soon`, `.wpcpm-people__muted` out of the selector list)
- Modify: `docs/sections/31-admin-settings.md` (the agreement wording row), `docs/sections/34-admin-operations.md` (the names table: the `wpcpm_agreement_drift` and `wpcpm_import_log` rows go; `wpcpm_report_state_version`'s row says it is no longer written)
- Test: `bin/test-agreement-template.php` (the drift section and its HTTP stand-ins go; the Doc fixture's load-bearing check and the checksum pin stay), `bin/test-institution-agreement.php`, `bin/test-institutions-index.php`, `bin/test-institutions-sync.php`, `bin/test-institutions-screen.php`, `bin/test-institution-import.php`, `bin/test-institution-import-form.php`, `bin/test-institution-create.php`, `bin/test-institution-invite.php`, `bin/test-semester-report.php`, `bin/test-settings.php`, `bin/test-institution-panel.php` (it reads `assets/css/institution.css`)

- [ ] Step 1: before anything is removed, `grep -rn` every name this task removes across `includes/`, `uninstall.php`, `wpcredits-program-manager.php`, `blocks/`, `assets/`, `~/GitHub` (the theme and the other plugins, the mirror copies aside) and `~/Projects/wpcredits-release`, and record that no reader outside the suites exists; a reader found stops the task and goes to the controller.
- [ ] Step 2: tests. `deactivate()` clears exactly the jobs `schedule_cron()` schedules, from the one list, and still hands the sync its own; uninstall unschedules `CRON_ASK` and the import tick whatever their arguments and deletes `wpcpm_agreement_drift`; `schedule_cron()` scheduling the drafting job on a site with no drafting day stores today, on one with a day keeps it, and with the job already scheduled reads no option; nothing hooks `WPCPM_Semester_Report::maybe_upgrade` any more and `set_state( 'final' )` is still refused; printing a report writes no `_wpcpm_report_exported`; checking a list writes no `wpcpm_import_log`; a stopped batch writes no `_wpcpm_batch_reason` and its audit row still carries the reason; accepting an invitation writes no `_wpcpm_inv_accepted`; the Settings row and the guide hold no "Check against the Doc"; the three CSS selectors are gone; and the suites' checks of every removed method go with the method.
- [ ] Step 3: run red.
- [ ] Step 4: implement, verbatim:
  - The agreement wording row's description: "The Google Doc the plugin's copy of the Collaboration Agreement was taken from, linked from the Agreement template card on the Institutions screen's Agreements tab; Google addresses only." The guide's line says the same.
  - `ACT_VIEW_SEMESTER_REPORT`: "The semester report, read and printed once approved." `ACT_EDIT_SEMESTER_REPORT`: "Generate, save, re-read consent, draft, approve, reopen, restore and ask: an Administrator's alone since 1.91.0."
  - Every other docblock named in Files says what the code does now (the stage writes and the terminal stages' refusal are the acceptance's, an agreement on file is recorded by hand on its institution's page, a missing card is a class not loaded, the screen is an Administrator's, the gap filter is the one the Pipeline tab offers), with no phase named.
- [ ] Step 5: green; the battery and the checks; `php bin/check-dead-annotations.php` finds no annotation left over a removed line.
- [ ] Step 6: `php bin/build-docs.php`.
- [ ] Step 7: commit, subject "Institutions: the code nobody calls and the docblocks of shipped phases go, and deactivation takes the daily jobs off the clock".

### Task 8 (coordinator): version, trial, deploy, after the deploy

- [ ] Version 1.123.27 in the plugin header, `WPCPM_VERSION` and `readme.txt`'s Stable tag; the changelog entry (the form's new address and title with the old one redirecting, plain words for schools, labels for Administrators, the email copy, the form copy, the removed code and the deactivation fix); the `.pot`.
- [ ] Docker copy, inside a snapshot put back afterwards: read `wpcpm_report_state_version` (R6) on the copy and, read only, on live; install the build; the first request moves the application page (slug, title, intro; `wpcpm_application_page_moved` stamped); `/apply/` and `/apply/?wpcpm_app_said=closed` answer 301 to the new address with the query kept; the form at 375px and 1440px, the country typed into and sent, a test application to an `example.test` address only where the copy's mail goes nowhere; an institution account's Institution Dashboard and the same through the switcher (Stage line only there); the Administrator Dashboard's cards; Tools > Emails previews of every changed email; `wp cron event list` before and after a deactivation and reactivation.
- [ ] Version: also the Administrator Dashboard block's `block.json` `version`, its `editor.asset.php` version and the pin at `bin/test-administrators-dashboard.php` ("versioned with the release that last changed what its editor script and description say"), to 1.123.27.
- [ ] Deploy only on the owner's yes, the live version read first; after it, read page 751's slug, title and content back (add the intro by hand if the content had been edited and the move left it), check `/apply/`, `/apply`, `/Apply/` and `/apply/?wpcpm_app_said=closed` on live, and search live's saved templates, template parts, synced patterns, and page and post content for `/apply/` (the saved Education Hub template holds two literal links) and point any hit at the new address, on the owner's yes for each write.
- [ ] Rollback, if a person judges it needed: install the 1.123.26 zip (the release script prints the line); then `wp post update 751 --post_name=apply` (the title and intro may stay, the form works under either), `wp option delete wpcpm_application_page_moved` so a later release moves the page again, and put back any link already pointed at the new address. Without the slug step /apply/ is a 404 under 1.123.26 (it has no redirect). Nothing else 1.123.27 stores is misread by 1.123.26 (`wpcpm_report_state_version` stays 2, the cron hooks keep their names, no saved email edit is touched).
- [ ] After the deploy (the owner's answer of 24 October 2026: yes): the handbook's Get Involved link, the mirror's README and the decks point at /institution-application/ (the owner edits the handbook, the coordinator the mirror), and the Airtable institution interest form (shrYvY0CDNIkILIre) is closed; the mirror and the installable zip as the release script does them.

---

## Self-review against section B

| Section B bullet | Where |
| --- | --- |
| The form's address, the redirect, the title and intro line | Task 1; the handbook and the mirror's links: Not in this release, Task 8 |
| Semester report lead promises the institution approves | Task 2 |
| The acknowledgement promises "send you the Collaboration Agreement to sign" | Task 5 |
| Airtable and internal words shown to schools (the contact tooltip, "Not yet in the Students table", "report record", raw statuses, the view ID, "Stage: Confirmed") | Task 2 |
| Raw state words on the Administrator Dashboard (Programs running, Institution applications) | Task 4 |
| "Mentor requests" becomes "Requests from institutions" | Task 4 |
| "the program's contact" means two people | Task 2 (the people card's mark); "Your contact at the program" stays the Administrator |
| Dates in four formats; the read line twice | Task 3 |
| The "Check against the Doc" promise and `drift()` | Task 7 (R7) |
| Dead code: `rebuild_all()`, `stage_counts()`, `staged_for()`, `invites_for()`, `exported_at()` / `mark_exported()` / `META_EXPORTED`, `_wpcpm_inv_accepted`, the import check log, the stopped-batch meta, the plugin CSS | Task 7; the theme's function and CSS: Not in this release |
| Stale phase docblocks; the `final` to `approved` migration on every init | Task 7 (R6) |
| Deactivation leaves the cron jobs scheduled | Task 7 (R8) |
| The Slack label that is wrong | Task 3 (R10) |
| "No start date" has no link to the edit form | Not in this release (waits on C1) |
| Email copy: the site's name without "the", report subjects, "agreement panel", Airtable and record IDs, Tools > Emails names | Task 5 |
| Form copy: sponsor labels, "practices", the unmarked required question, the country select | Task 6 |
| wp-admin: "Sync and storage", technical words, "Semester reports 2" against "1", four names for the same people | Task 4 (technical words, the count); Not in this release (the tab's split, the one name) |
