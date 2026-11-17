# The Institution Dashboard in the frame, on the shared parts: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the Institution Dashboard into the dashboards' frame (a side menu, a view per item, the head, the Overview, viewing as, the guide and the updates in the frame) and then draw every one of its cards on the shared parts, while every other surface of the site prints and looks exactly as before.

**Architecture:** `WPCPM_Institutions_Dashboard` gains the Sponsor Dashboard's view router (`view()`, `view_url()`, `view_of()`, `card_url()`, `keep_institution()`, `requested_record()`), a menu built from the same policy questions its cards ask (`views_for()`), what waits and what is news (`waiting_items()`), and a `render()` that opens `WPCPM_Dashboard_Frame` and draws one view. Stage A does all of that with the `institution` surface still off: the existing cards print their old markup inside their views, and the new blocks (head, Overview, choice state) are drawn with the parts' helpers, which have no old markup. Stage B gives each card a parts' branch behind `WPCPM_UI::on()`, then one task adds `institution` to `WPCPM_UI::LIVE` and deletes what only this page used. The theme follows with its own tasks: the bare canvas and the frame names (Stage A), the dead rules (Stage B).

**Tech Stack:** WordPress plugin PHP (stand-alone suites run as `php bin/test-*.php` from the plugin root, with stand-ins for WordPress); node harnesses in `bin/js/` run by a PHP suite; the guide's builder (`php bin/build-docs.php`, run twice); the picture rig under `bin/ui/` and a new layout harness under `bin/ui/layout/` (node, `playwright-core` with Chrome) that measures the suites' own markup at main-area widths, no site.

**Spec:** `docs/specs/2026-11-15-institution-dashboard-frame-design.md` (commit `c0dfdf0a`). Section numbers below ("design 6.4") are that document's; "parts design 2.4" is `docs/specs/2026-11-13-ui-shared-parts-design.md`.

**Branch and worktree:** `feature/institution-dashboard-frame` in `.worktrees/ui-shared-parts`, from plugin 1.123.94. **Plugin version 1.123.95**, then **theme 1.32.28** the same day, unless the version task's read of live, main and every branch shows them taken; then the next free (release 1's theme Task 20 was also planned as 1.32.28: if it ships first, this theme release takes the next number). Theme tasks are done in `~/GitHub/wpcredits-theme` on branch `feature/institution-dashboard-frame` from its `main` (`46f8376`, 1.32.27).

**Release:** one release, built in two stages (A: the frame, the views, the routing and every way back, `institution` off; B: every card on the parts, `institution` on), each ending in a picture gate run by the controller. Nothing is released before 20 November 2026 and nothing without the owner's yes: this plan ends at "ready for the owner's pictures".

## The controller's rulings (binding; where they differ from the design, these win)

1. The roster's four groups keep today's open rule (design 6.1, builder's call 4): Current and Waiting for a mentor open, Finished and Not on the program now closed, any group of more than twelve (`WPCPM_Institution_Roster_View::OPEN_MAX`) closed. Every other card of a view is drawn open.
2. The Sponsor Dashboard's "Viewing as sponsor" control is not touched. `WPCPM_Dashboards::render_switcher()` keeps today's markup for every caller but the Institution Dashboard.
3. The site does not remember the last school an Administrator chose. "Choose an institution" is the first state of an Administrator's page whose address names no school.
4. On the Overview, a semester report approved in the last 30 days and a details request answered in the last 30 days are news, not things that wait: they are rows AFTER the waiting rows, with the neutral tag "New" (not "Waits"), and no menu count includes them. "Waits" means the school has to act.
5. One release, plugin 1.123.95, then theme 1.32.28 the same day; two stages, each ending in a picture gate; nothing released before 20 November 2026 and not without the owner's yes.

## The planner's rulings (where the design leaves a choice, or cannot be built as written)

- **P1. The Administrator's own row.** For an Administrator an unsettled agreement is one row, first, titled "The agreement is not settled", tagged "Waits" and counted (it waits on somebody: the school, or the Administrator reading it); it replaces the school's agreement row for that reader, so an Administrator never sees two agreement rows.
- **P2. New blocks are drawn with the parts from Stage A.** The head, the figures, the waiting and news rows, the contact card and the choice state have no "before"; they use `WPCPM_UI` helpers whose classes (`wpcpm-ui-*`) parts.css styles whatever the switch says. Only `.wpcpm-btn` and `.wpcpm-status` change look under `.wpcpm-ui-on`, so the Overview's buttons and tags draw in the frame's own look in Stage A and in the parts' look in Stage B.
- **P3. Enroll students in the head** is printed on the Overview's head only, as the Sponsor Dashboard's New offer is, and only while `enroll` is in the reader's menu.
- **P4. The whys of the waiting rows are today's sentences, extracted, never retyped:** each comes from a new public method on the class that prints it today (`WPCPM_Institution_Panel::state_sentence()`, `WPCPM_Institution_Import_Form::waiting_sentence()`, `WPCPM_Institution_Details_Card::outcome_sentence()`), and the card that printed it calls the same method, pinned byte for byte.
- **P5. A view the reader may not open is never drawn:** `view()` answers a view outside `views_for()` with the Overview. An old address is inferred first (design 9), then gated the same way.
- **P6. "Viewing as institution" in Stage A is today's switcher,** above the head as on the Sponsor Dashboard, carrying the view; the compact control of design 7 (no Show with script, choosing loads the page) is Stage B's Task 14, behind `WPCPM_UI::on()`. The choice state's behavior (no first-school fallback for an Administrator) is Stage A.
- **P7. The roster's groups are not `WPCPM_Dashboard_Card`s:** a card shell drawn while a view unfolds its cards is always open, which ruling 1 forbids. Each group prints the card fold's markup through `WPCPM_UI::fold_head()` in its own `<section><details>`.
- **P8. Strings the Administrator guide names in bold stay in the code until the guide task.** `bin/test-guide-labels.php` checks every bold name of the covered sections (30 to 35, 60 to 67) against the code. A task that stops printing such a string ("Semester report", "Representatives and agreement" in `33-admin-access.md:201`, among others) keeps its definition in place, unused, with the comment `// Named by the Administrator guide until Task 25.`, and Task 25 deletes it with the guide's edit. A task finds them by running `php bin/test-guide-labels.php` after its change.
- **P9. The file box** (parts design 2.4, design 6.2 and 6.5) is a new helper, `WPCPM_UI::file()`, and a new job of `assets/js/parts.js` (parts design 3.2 job 3), Task 13; release 1 built neither.
- **P10. The layout harness is committed,** generalized from `.superpowers/sdd/2026-11-13-ui-shared-parts-release-1/harness-18f/` into `bin/ui/layout/` (Task 1), so every worker measures before reporting; release 1's fix loops ended only when a worker could measure.

## Global Constraints

- **The rule above all: every other surface prints and looks exactly as before.** The Administrator Dashboard, the Sponsor Dashboard (its "Viewing as sponsor" included), both Report Cards, every wp-admin screen and tab (Manage members, the manager's agreement upload, the Semester reports tab), every email. Proved by (1) these suites passing with no edit to them: `bin/test-sponsors-dashboard.php`, `bin/test-administrators-dashboard.php`, `bin/test-dashboard-card.php`, every `bin/test-ui*.php`, `bin/test-administrator-guide.php`, `bin/test-student-modules.php`, `bin/test-dashboard-switcher.php`, `bin/test-parts-js.php`; (2) every shared renderer asserted on both branches in the task that gives it a parts' branch (the old branch's output pinned by an md5 digest of today's output, taken from the code at `c0dfdf0a`); (3) the controller's pictures: every scene whose area is not `institution-dashboard`, `institution-guide` or `institution-update` compares `same`, pixel for pixel, at every width.
- **Behavior does not change:** same forms, field `name`s, values, nonce actions, `action`s, handlers, messages, permissions and data. A form's posted fields are asserted unchanged (the list of `name=value` pairs and the nonce's action, form by form, old branch against new). Words change only where design 12 lists them. A refusal keeps today's sentence, tied to its box (`WPCPM_Form_Errors`); kept typing stays (`WPCPM_Typed_Text`). A field id that repeats on one page today may become unique per item; every anchor and id a mail, redirect, link, guide or script points at stays where it is (design 9 names them).
- **Shared renderers keep their old branch** for the surfaces still off (design 11): `WPCPM_Institution_People::render_member()` and `WPCPM_Institution_Panel::render_upload_form()` (wp-admin's Manage members and the manager's upload), `WPCPM_Dashboards::render_switcher()` (the Sponsor Dashboard, the Report Cards), `WPCPM_Sponsor_Event_List::render()` and `render_actions()` (the Report Cards' calendars), `WPCPM_Semester_Report_Screen` (wp-admin's Semester reports tab), `WPCPM_Dashboard_Card`'s old branch (the Sponsor Dashboard), `WPCPM_Module_Order` (the Student Report Card's modules). No old branch is deleted unless a search, recorded in the commit message, shows no surface left on it.
- **The owner's words:** "Administrators" is the one name for the people who run the program ("program manager" appears nowhere added here); full product names ("Institution Dashboard", "Administrator Dashboard", "Sponsor Dashboard", "Student Report Card", "Mentor Report Card", "Collaboration Agreement", "Education Directory"); plain hyphens only, never an em or en dash, in strings, comments, docs, tests and commit messages; never "out of force" in a new string; nothing links to Airtable (no new link to an Airtable form or base anywhere); a track may have no hours target (never ask or guard on one). US English.
- **No real names:** no person's, school's or company's real name in code, tests, fixtures, the rig or docs: invented names (every one holding "Example") and `example.org` / `example.com` / `example.test` addresses only. No personal path or account in a committed file: a local tool is found through an environment variable with a default built from the home folder.
- **Nothing wider than its card, at any width, measured BEFORE a worker reports.** Every task that prints markup on this dashboard registers what its suite draws with the layout harness (Task 1) and runs it at the main-area widths **358, 569, 736, 799, 800, 880, 936, 960**, with the suite's long fixture: a 40-character word in a school's name (`Exampleinstituteforappliedwebengineerings`), in a student's name, in an address (`averyveryveryverylongmailboxname1234@example.org`) and in a tutor's name, and long lists (13 students in one group, 6 languages, 4 tracks, 5 people, 3 invitations). The report quotes the harness's last line, which must be `0 faults`. Faults found only between 601 and 1000px of window (main area about 358 to 799px) are the commonest: the parts' phone forms switch at 800px of main area, not at the 600px window.
- **Tables:** none on this dashboard is `wide` by default (design 6). A table's least width is measured in the harness (the narrowest main-area width at which it shows no fault as a table); a table whose least width exceeds 800px takes `wide` (880, or 936 when it needs that much), and the measure is written in the commit message. A cell holding an address, an email or a website is the token kind (it may break anywhere); every other cell keeps ordinary wrapping. A typed name in a table (a student's, a tutor's, a person's, a school's) goes through `WPCPM_UI::breakable()`. Dates whole: a day and a month and a year never split (`WPCPM_UI::whole()`).
- **Rhythm and look:** the parts' values only (parts design 2.1 to 2.13): rhythm 14 / 16 / 24px, nothing under 12px, corners 2px, flat; 40px controls; 56px card heads and 44px inner heads; under 800px of main area every part takes its phone form.
- **Translatable strings:** new strings through `__()`, `esc_html__()`, `_n()`, with a `/* translators: */` comment where they hold a placeholder. After any change to a translatable string (added or removed), regenerate the `.pot` with the command `bin/test-translators-pot.php` names at its top, and run that suite.
- **Standards:** `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" phpcs -d memory_limit=2G <the PHP files you touched>` (0 errors, no warning you added), and before you report the whole check once: `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" phpcs -d memory_limit=2G`, whose last line must read 0 errors and at most **176** warnings. Do not trust `bin/check-standards.sh`: it runs out of memory at PHP's default limit and then exits 0.
- **Static analysis** when you touched PHP under `includes/`: `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" phpstan analyse -c "$HOME/Projects/wpcredits-release/kit/phpstan.neon" --error-format=json --no-progress --memory-limit=2G includes blocks wpcredits-program-manager.php uninstall.php > /tmp/phpstan.json; python3 -c "import json;print(json.load(open('/tmp/phpstan.json'))['totals'])"` must report `file_errors` of at most **133**. Never pass a string callback (`'strlen'`, `'trim'`) to `array_filter`, `array_map` and the like: use a closure.
- **The battery,** before every commit: `n=0; f=0; for t in bin/test-*.php; do php "$t" > /tmp/suite.out 2>&1 || { f=$((f+1)); echo "FAIL $t"; tail -15 /tmp/suite.out; }; n=$((n+1)); done; echo "suites=$n failed=$f"` must end with `failed=0`. The baseline at `c0dfdf0a` is `suites=201 failed=0`; each new suite this plan adds raises `n` by one, and the report says the new count. When a `docs/sections` file changed: `php bin/build-docs.php` twice, then `php bin/test-guide-labels.php`, `php bin/test-guide-jobs.php`, `php bin/test-build-docs.php`, `php bin/test-administrator-guide.php`.
- **Tests first:** every behavior and every printed part gets a check that fails before the code is written, in the suite the task names, in the repository's style (a plain PHP script, `ck( $label, $actual, $expected )` printing `ok   ` or `FAIL `, a stand-in for each WordPress function it needs, exit 1 when any check failed). A new suite copies the stand-ins it needs from `bin/test-institutions-dashboard.php:112-560` and `bin/test-sponsors-dashboard.php`. What is posted, refused or kept is asserted unchanged, never weakened; an existing assertion is changed only where the markup it pins changes by design, and the report lists each.
- **Git:** commit by explicit path list (`git add <path> <path>` then `git commit -m "..." -- <path> <path>`); never `git add -f`, `git add -A` or `git add .`; nothing under `.superpowers` staged; never `git stash` in any form; no push; one commit per task with the message the task gives, ending with your own session's `Co-Authored-By:` line. One editor in the worktree at a time: the controller dispatches one implementer at a time and cherry-picks a rig operator's commits only between tasks.
- **No version bump** (plugin header, `WPCPM_VERSION`, `readme.txt` Stable tag, theme `style.css` and `readme.txt`) until Task 27.
- Implementers never use Docker, ssh, the live site, Airtable or Slack, never run the picture rig against a site, never dispatch subagents. Running the rig is the controller's job (the gate briefs at the end). The layout harness (Task 1) needs no site and is every worker's to run.
- If the task's text and the code disagree (a function not where the task says, a suite that asserts something the task forbids), stop and report NEEDS_CONTEXT with what you found; do not guess. Line numbers below were read at `c0dfdf0a`; later tasks find functions by name.
- Report: status (DONE, DONE_WITH_CONCERNS, NEEDS_CONTEXT or BLOCKED), commit, each check written with its red and its green, the last lines of the battery, standards, static analysis and the layout harness, anything decided that the task did not, anything nearby that looks wrong.

## Review Focus

Five failure modes that would hurt a school's person or an Administrator and that no single task's own test would catch. Each has its test in the task that owns the code; the reviewer of that task checks the test exists and fails on the old code.

1. **An old address, an emailed link or a bookmark lands on the wrong view, or on none.** A student's card link in a mail (`?wpcpm_institution_student=12`), a report link (`?wpcpm_report=2026-H1`), an enrollment batch (`?wpcpm_batch=7`), the Administrator's assignment mail (`#wpcpm-details` with the school), a guide link to `#wpcpm-import`, and the agreement-accepted mail (the bare page) each open the view that holds the card, and the card is on the page. Tests: Task 2 (`view()` inference, every row of the table there), Task 9 (every link built through `card_url()`), Task 10 (an anchor-only link moves to its view).
2. **A handler's way back loses the Administrator's chosen school, or a member's way back gains one.** After an Administrator viewing school X presses any form on any view, the redirect names `wpcpm_view=<that view>` and `wpcpm_institution_view=X`; after a representative presses the same form, it names the view and never a school. Test: Task 9 (`bin/test-institution-ways-back.php`, one row per handler, as an Administrator and as a member).
3. **A locked school (agreement not settled) sees, or reaches by address, a view it may not open.** A locked representative's menu holds Overview and Agreement only (with Program updates and Help); `?wpcpm_view=students`, `?wpcpm_view=enroll`, `?wpcpm_institution_student=12` and `?wpcpm_report=2026-H1` all draw the Overview, and no roster, import or report card is called. Tests: Task 3 (`views_for()`), Task 5 (the render gate, by the stand-in's call log).
4. **A tutor's narrower rights widen.** A tutor of a settled school gets every view but Enroll students and Events; `?wpcpm_view=enroll` and `?wpcpm_view=events` draw the Overview; the Overview offers no Enroll students button and no row that opens Enroll students; what the cards show a tutor is what they show today. Tests: Task 3, Task 4 (rows by view), Task 6 (the head's button).
5. **A card's message is printed on a view the reader is not on, or is eaten there.** A card takes its flash when it renders; a handler that sent the reader to the wrong view would leave the flash for a later visit, or a view that draws another card could print it. Every handler's way back names the view whose card takes that handler's flash, and a view that does not draw a card leaves that card's flash untaken. Tests: Task 9 (redirect view equals the view of the card that takes the flash, per handler), Task 5 (on the Overview, with the import form's and the details card's flashes set, neither is printed and both are still set afterwards).

---

## Pre-flight: tasks that share a file or an interface

| Tasks | Shared | What one produces, what the other consumes | Ruling |
|---|---|---|---|
| 2, 3, 4, 5, 6, 7, 8, 10, 11 | `includes/modules/class-wpcpm-institutions-dashboard.php` | each adds to the class in order: 2 the router, 3 `views_for()`, 4 `waiting_items()`, 5 `render()` in the frame, 6 the Overview, 7 the choice state, 8 `menu_args()`, 10 the anchor map, 11 nothing (the guide view consumes 8) | in order; each task finds the methods by name |
| 2 to 9, 10, 15 to 23 | `WPCPM_Institutions_Dashboard::VIEW_CARDS`, `card_url()`, `view_of()` | 2 produces; 4 (counts by view), 5 (dispatch), 9 (ways back), 10 (anchor map), 15 to 23 (anchors kept) consume | a card key added later (none expected) is added to `VIEW_CARDS` in the task that adds the anchor, with its test row in `bin/test-institution-views.php` |
| 3, 4, 5, 6, 7, 8 | `views_for( $record, $viewer, $can_manage )` | 3 produces; 4 (a row only for a view the reader has), 5 (menu and gate), 6 (head button), 7 (choice list), 8 (guide and update menus) consume | signature fixed in Task 3 |
| 4, 6, 21 | `WPCPM_Institution_Panel::state_sentence()` | 4 produces (extracted from `render_state()`); 6 prints it in rows; 21 converts `render_state()` and must keep calling it | Task 21 may not retype the sentence |
| 4, 6, 17 | `WPCPM_Institution_Import_Form::waiting_sentence()` | 4 produces; 17 converts the form and keeps calling it | as above |
| 4, 6, 19 | `WPCPM_Institution_Details_Card::outcome_sentence()` | 4 produces; 19 converts `render_outcome()` and keeps calling it | as above |
| 6, 15 | `class-wpcpm-institution-roster-view.php` | 6 adds `opening_cohort()` and has `render()` use it; 15 converts `render_filters()`, `render_strip()`, `render_group()`, `render_student()`, `render_unlinked()` | in order; 15 keeps `opening_cohort()` |
| 5, 9, 16, 18 | the back links "Back to the roster" (`WPCPM_Institution_Student_View::render_back()`) and "Back to the other semesters" (`WPCPM_Semester_Report_Screen::render_reading()`, `render_editor()`) | 9 stops printing them in the Institution Dashboard (menu items carry the way back, built in 5); 16 and 18 convert what is left | CONFLICT found: the wp-admin Semester reports tab draws `render_editor()` too and stays off. Ruling: Task 9 removes the back link only where `WPCPM_UI::surface() === 'institution'`; the wp-admin tab keeps it |
| 7, 14 | `WPCPM_Dashboards::render_switcher()`, `assets/js/switcher.js` | 7 calls today's switcher with `keep`; 14 adds the compact parts' branch, taken only when `WPCPM_UI::on()` and `WPCPM_UI::surface() === 'institution'` | `bin/test-dashboard-switcher.php` passes untouched after 14 |
| 13, 17, 21 | `WPCPM_UI::file()` and `parts.js`'s file job | 13 produces; 17 (Or a list) and 21 (the upload form) consume | signature fixed in Task 13 |
| 8, 11 | `menu_args()`; `WPCPM_Administrator_Guide_View`, `WPCPM_Administrator_Updates_View` | 8 produces `menu_args( 'guide' \| 'updates' )`; 11 makes the two views call it for the `institution` reader and audience | in order |
| 12, 24 | the theme's `functions.php`, `inc/dashboard.php`, `templates/`, `assets/css/dashboard.css`, `bin/check-selectors.php` | 12 adds the frame names, the predicate and the templates; 24 deletes the dead rules and pins against the plugin at Task 23 | separate commits in the theme repository; 24 runs after 23 |
| 23, 25 | the strings that stop printing, the `.pot`, the guide's suites | 23 deletes old branches; 25 rewrites the guide and deletes the strings P8 kept | CONFLICT found (the ledger of release 1 hit it): a string the covered guide sections name in bold cannot leave the code before the guide does. Ruling P8: 23 and every earlier task keep such strings, unused and commented; 25 deletes them with the guide edit. The battery stays green at every commit |
| 22, 9 | `class-wpcpm-sponsor-event-list.php` | 9 routes `page_of( BACK_INSTITUTION )` through `card_url( 'sevents' )`; 22 adds the parts' branch of `render()` and `render_actions()` | in order |
| 26, 15 to 22 | `bin/ui/scenes.mjs`, `bin/ui/seed.mjs`, `bin/ui/states.php` | 26 adds the scenes for the views and open states the tasks built; the scenes' selectors are the anchors of `VIEW_CARDS` | 26 before Gate B's run; the Stage A gate uses the scenes Task 12b adds (below) |
| each task with itself | tests against code | nothing found on a read of the Files lists against the code at `c0dfdf0a`; the review loop is the net | - |

---

## Stage A: the frame, the views, the routing and every way back (`institution` off)

### Task 1: The layout harness, committed, with no site

The workers of release 1 could not see faults that only showed between 601 and 1000px; the loop ended when a harness measured the suites' own markup in headless Chrome. This task commits that harness, generalized, so every later task measures before it reports.

**Files:**
- Create: `bin/ui/layout/dump.php`, `bin/ui/layout/page.mjs`, `bin/ui/layout/measure.js`, `bin/ui/layout/run.mjs`.
- Modify: `bin/ui/README.md` (three lines under the existing six: what the harness is and its one command); `bin/test-ui-rig.php` (new checks, below).
- Source to read, not to copy blindly: `.superpowers/sdd/2026-11-13-ui-shared-parts-release-1/harness-18f/` (`build.py`, `dump.php`, `measure.js`, `run.sh`): it hard-codes a home folder and the Administrator Dashboard's cases; neither may come across.

**Interfaces:**
- Produces, `bin/ui/layout/dump.php` (loaded with `php -d auto_prepend_file=bin/ui/layout/dump.php bin/test-x.php`): `function wpcpm_layout_keep( string $name, string $html ): void` stores the markup under its name; when the environment variable `WPCPM_LAYOUT_OUT` names a file, a shutdown function writes every kept case there as JSON (`{ name: html }`). Without the variable nothing is written and the suite runs as it always does. Suites call it guarded: `if ( function_exists( 'wpcpm_layout_keep' ) ) { wpcpm_layout_keep( 'Students: groups, long', $html ); }`.
- Produces, `bin/ui/layout/run.mjs`: `node bin/ui/layout/run.mjs --in <cases.json> [--out <result.json>] [--widths 358,569,...] [--only <substring>]`. Exported: `export const WIDTHS = [358, 569, 736, 799, 800, 880, 936, 960];`. For each width and case it builds the page (`page.mjs`), opens it in Chrome headless (Playwright from `process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs')`, `chromium.launch({ channel: 'chrome', headless: true })`), runs `measure.js`, and prints one line per case and width: `ok   <case> <width>` or `FAULT <case> <width> <what>`; then `<N> faults`. Exit 1 on any fault.
- Produces, `bin/ui/layout/page.mjs`: `export function page(cases, widths, { pluginDir, themeDir }): string`: one HTML document; each case at each width is `<div class="h-case" data-name data-w><div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on wpcpm-frame"><div class="wpcpm-frame__main" style="box-sizing:content-box;width:{w}px;container:wpcpm-main/inline-size">{html}</div></div></div>`; the plugin's sheets `parts.css`, `dashboard-frame.css`, `dashboard.css`, `institution.css`, `report-print.css` linked from `pluginDir/assets/css/` (default: the repository root), and the theme's `style.css` and `assets/css/dashboard.css` from `themeDir` (`process.env.WPCPM_THEME_DIR || join(homedir(), 'GitHub/wpcredits-theme')`, skipped with a printed `no theme` line when absent); Inter from the theme's `assets/fonts/inter/inter-latin.woff2`; the body classes `wpc-dashboard-page wpc-institution-page`. `--off` prints the cases without `wpcpm-ui-on`, for an old branch.
- Produces, `bin/ui/layout/measure.js` (in the page): opens every `details`; per case reports `OVERFLOW <text> by <px>` for any element whose right edge passes the content edge of the nearest `.wpcpm-ui-fold__body`, `.wpcpm-ui-panel` or `.wpcpm-frame__main` by more than 0.5px; `CELL <text> (<scroll> > <client>)` for any element whose `scrollWidth` exceeds its `clientWidth` by more than 1 (an element with `overflow-x: auto` excepted only when it carries `data-wpcpm-scrolls`); `H-SCROLL <px>` when the main area's `scrollWidth` exceeds its `clientWidth`; and for each `table.wpcpm-ui-table` its form (`table`, `one column`, `lines a+b`), so a worker can read where it stacks.

- [ ] **Step 1: Write the failing checks** in `bin/test-ui-rig.php`, beside its existing ones:
  ```php
  foreach ( array( 'dump.php', 'page.mjs', 'measure.js', 'run.mjs' ) as $f ) {
  	ck( "bin/ui/layout/$f exists", is_file( WPCPM_PLUGIN_DIR . "bin/ui/layout/$f" ), true );
  }
  $all = implode( "\n", array_map( static function ( $f ) { return (string) @file_get_contents( $f ); }, glob( WPCPM_PLUGIN_DIR . 'bin/ui/layout/*' ) ) );
  ck( 'the harness holds no home folder', preg_match( '#/(?:Users|home)/[A-Za-z]#', $all ), 0 );
  ck( 'the harness holds no site address', preg_match( '#https?://(?!www\.w3\.org)#', $all ), 0 );
  ck( 'the harness never calls the copy', false === strpos( $all, 'refuseUnlessCopy' ), true );
  ck( 'the eight main-area widths', (bool) preg_match( '/export const WIDTHS = \[358, 569, 736, 799, 800, 880, 936, 960\];/', $all ), true );
  ck( 'dump.php writes only when asked', false !== strpos( $all, "getenv( 'WPCPM_LAYOUT_OUT' )" ), true );
  ```
  and a node part (skipped with a `skip` line when `node` is not on the path, as the suite already does): `bin/js/ui-rig.js` gains a check that `page([{...}], [358], {...})` holds `width:358px` and `container:wpcpm-main/inline-size` once per case.
- [ ] **Step 2: Run** `php bin/test-ui-rig.php`. Expected: the new checks fail.
- [ ] **Step 3: Implement** the four files. Then prove it on today's markup: add one guarded `wpcpm_layout_keep( 'Institution Dashboard today, representative', $html )` call to `bin/test-institutions-dashboard.php` where it draws the whole page for a representative, run `WPCPM_LAYOUT_OUT=/tmp/cases.json php -d auto_prepend_file=bin/ui/layout/dump.php bin/test-institutions-dashboard.php` and `node bin/ui/layout/run.mjs --in /tmp/cases.json --off`. Expected: it runs to the end and prints a faults line (today's faults at 358 are known: two Enroll students selects and the History meta lines; this proves the harness sees them). Quote the line in the report.
- [ ] **Step 4: Run** `php bin/test-ui-rig.php` and the battery. Expected: all pass.
- [ ] **Step 5: Commit:** `UI rig: a layout harness that measures the suites' own markup at eight main-area widths, no site` (paths: the four new files, `bin/ui/README.md`, `bin/test-ui-rig.php`, `bin/js/ui-rig.js`, `bin/test-institutions-dashboard.php`).

### Task 2: The views and their addresses

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (constants after `SLUG` at `:61`; new public methods before `render()` at `:314`).
- Test: `bin/test-institution-views.php` (new).

**Interfaces:**
- Consumes: `WPCPM_Institution_Roster::ARG_VIEW` (`wpcpm_institution_view`), `WPCPM_Institution_Roster_View::ARG_COHORT`, `ARG_STATUS`, `ARG_SEARCH`, `ARG_STUDENT`, `WPCPM_Institution_Import_Form::ARG_BATCH`, `WPCPM_Semester_Report_Screen::ARG`, `WPCPM_Mentors_Sync::is_record_id()`, `WPCPM_Institutions_Index::has()`, `page_url()` (`:1456`).
- Produces, on `WPCPM_Institutions_Dashboard`:
  ```php
  const VIEW_ARG            = 'wpcpm_view';
  const VIEW_OVERVIEW       = 'overview';
  const VIEW_STUDENTS       = 'students';
  const VIEW_ENROLL         = 'enroll';
  const VIEW_REPORTS        = 'reports';
  const VIEW_INSTITUTION    = 'institution';
  const VIEW_AGREEMENT      = 'agreement';
  const VIEW_EVENTS         = 'events';
  const VIEW_ADMINISTRATORS = 'administrators';
  const VIEW_GUIDE          = 'guide';
  const VIEW_UPDATES        = 'updates';
  const FRAME_PAGE          = 'institution';
  /** The cards each view draws, by their anchors without `wpcpm-`: what `card_url()` routes by. */
  const VIEW_CARDS = array(
  	'students'       => array( 'roster', 'student-status', 'student-form', 'student-invite' ),
  	'enroll'         => array( 'import' ),
  	'reports'        => array( 'report' ),
  	'institution'    => array( 'details', 'people', 'tutors' ),
  	'agreement'      => array( 'agreement' ),
  	'events'         => array( 'sevents' ),
  	'administrators' => array( 'for-administrators', 'admin-answers', 'admin-history' ),
  );
  public static function title(): string;                 // __( 'Institution Dashboard', ... ), the existing string
  public static function asked_view(): string;            // the view the address asks for, inferred for an old address; never gated
  public static function view_url( string $view = self::VIEW_OVERVIEW, string $record = '', array $args = array() ): string;
  public static function view_of( string $card ): string; // a VIEW_* key; the Overview for a card no view lists
  public static function card_url( string $card, string $record = '', string $item = '', array $args = array() ): string;
  public static function requested_record(): string;      // the school an Administrator's address names, or ''
  public static function keep_institution( string $url, string $record ): string;
  ```
  - `asked_view()`: `$_GET[ VIEW_ARG ]` through `sanitize_key()` when it is a key of `VIEW_CARDS`; otherwise, for an address with no `wpcpm_view` (design 9): `students` when the request has `wpcpm_institution_student`, `wpcpm_cohort`, `wpcpm_roster_status` or `wpcpm_roster_search`; `enroll` when it has `wpcpm_batch`; `reports` when it has `wpcpm_report`; else `overview`. An explicit `wpcpm_view` that is not a key is the Overview (no inference). The gate against the reader's views is `view()` in Task 5 (ruling P5).
  - `view_url()`: `page_url()`, with `wpcpm_view` for any view but the Overview, then `$args` (each scalar non-empty value), then `wpcpm_institution_view` when `$record` is a record ID. '' while the page has no address.
  - `card_url()`: `view_url( view_of( $card ), $record, $args ) . '#' . ( '' !== $item ? $item : 'wpcpm-' . sanitize_key( $card ) )`; '' while the page has no address.
  - `requested_record()`: '' unless `current_user_can( WPCPM_Roles::CAP_MANAGE )`; then the request's `wpcpm_institution_view`, trimmed, when it is a record ID the index has.
  - `keep_institution()`: the URL with `wpcpm_institution_view` set to `$record` when both are non-empty, else the URL as given (the Sponsor's `keep_sponsor()`).

- [ ] **Step 1: Write the failing checks** in `bin/test-institution-views.php` (stand-ins: `get_option`, `get_post_status` returning `publish`, `get_permalink` returning `https://example.test/institution-dashboard/`, `add_query_arg` as WordPress builds it, `current_user_can` from `$GLOBALS['manage']`, `$_GET`):
  ```php
  $page = 'https://example.test/institution-dashboard/';
  $rec  = 'recEXA00000000001';
  ck( 'the Overview is the bare page', WPCPM_Institutions_Dashboard::view_url(), $page );
  ck( 'a view names itself', WPCPM_Institutions_Dashboard::view_url( 'students' ), $page . '?wpcpm_view=students' );
  ck( 'an Administrator keeps the school', WPCPM_Institutions_Dashboard::view_url( 'enroll', $rec ), $page . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec );
  ck( 'an unknown view is the Overview', WPCPM_Institutions_Dashboard::view_url( 'nope' ), $page );
  $cards = array( 'roster' => 'students', 'student-status' => 'students', 'student-form' => 'students', 'student-invite' => 'students', 'import' => 'enroll', 'report' => 'reports', 'details' => 'institution', 'people' => 'institution', 'tutors' => 'institution', 'agreement' => 'agreement', 'sevents' => 'events', 'for-administrators' => 'administrators', 'admin-answers' => 'administrators', 'admin-history' => 'administrators' );
  foreach ( $cards as $card => $view ) {
  	ck( "card $card is on view $view", WPCPM_Institutions_Dashboard::view_of( $card ), $view );
  	ck( "card_url( $card ) ends at its anchor", substr( WPCPM_Institutions_Dashboard::card_url( $card ), -strlen( "#wpcpm-$card" ) ), "#wpcpm-$card" );
  }
  ck( 'a card no view lists is on the Overview', WPCPM_Institutions_Dashboard::view_of( 'nothing' ), 'overview' );
  ck( 'an item anchor replaces the card one', WPCPM_Institutions_Dashboard::card_url( 'import', '', 'wpcpm-import-file' ), $page . '?wpcpm_view=enroll#wpcpm-import-file' );
  ck( 'args travel before the anchor', WPCPM_Institutions_Dashboard::card_url( 'report', $rec, '', array( 'wpcpm_report' => '2026-H1' ) ), $page . '?wpcpm_view=reports&wpcpm_report=2026-H1&wpcpm_institution_view=' . $rec . '#wpcpm-report' );
  // Review Focus 1: old addresses.
  $old = array(
  	array( array( 'wpcpm_institution_student' => '12' ), 'students' ),
  	array( array( 'wpcpm_cohort' => '2026-H1' ), 'students' ),
  	array( array( 'wpcpm_roster_status' => 'waiting' ), 'students' ),
  	array( array( 'wpcpm_roster_search' => 'Example' ), 'students' ),
  	array( array( 'wpcpm_batch' => '7' ), 'enroll' ),
  	array( array( 'wpcpm_report' => '2026-H1' ), 'reports' ),
  	array( array( 'wpcpm_institution_view' => $rec ), 'overview' ),
  	array( array(), 'overview' ),
  	array( array( 'wpcpm_view' => 'agreement', 'wpcpm_report' => '2026-H1' ), 'agreement' ),
  	array( array( 'wpcpm_view' => 'bogus', 'wpcpm_batch' => '7' ), 'overview' ),
  );
  foreach ( $old as $case ) {
  	$_GET = $case[0];
  	ck( 'asked view for ' . http_build_query( $case[0] ), WPCPM_Institutions_Dashboard::asked_view(), $case[1] );
  }
  $GLOBALS['manage'] = false; $_GET = array( 'wpcpm_institution_view' => $rec );
  ck( 'a member never names a school', WPCPM_Institutions_Dashboard::requested_record(), '' );
  $GLOBALS['manage'] = true;
  ck( 'an Administrator names the school', WPCPM_Institutions_Dashboard::requested_record(), $rec );
  $_GET = array( 'wpcpm_institution_view' => 'not-a-record' );
  ck( 'a malformed school is none', WPCPM_Institutions_Dashboard::requested_record(), '' );
  ck( 'keep_institution adds the school', WPCPM_Institutions_Dashboard::keep_institution( $page, $rec ), $page . '?wpcpm_institution_view=' . $rec );
  ck( 'keep_institution leaves a member link alone', WPCPM_Institutions_Dashboard::keep_institution( $page, '' ), $page );
  ```
- [ ] **Step 2: Run** `php bin/test-institution-views.php`. Expected: fails (no methods).
- [ ] **Step 3: Implement** the constants and the seven methods, each with a docblock that says what the Sponsor Dashboard's twin is.
- [ ] **Step 4: Run** the new suite, `bin/test-institutions-dashboard.php` and the battery. Expected: all pass (`suites=202`).
- [ ] **Step 5: Commit:** `Institution Dashboard: views, their addresses and a link to every card, old addresses inferred` (paths: the class, the new suite).

### Task 3: Which views a reader may open

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php`; `includes/modules/class-wpcpm-sponsor-event-list.php` (one new public method beside `render()` at `:142`).
- Test: `bin/test-institution-views.php`; neighbor `bin/test-sponsor-event-list.php`.

**Interfaces:**
- Consumes: `WPCPM_Institution_Policy::decide( $act, WPCPM_Institution_Policy::subject_institution( $record ), $viewer )` with `ACT_VIEW_ROSTER`, `ACT_ADD_STUDENT`, `ACT_VIEW_SEMESTER_REPORT`; `WPCPM_Institution_Import_Form::enabled()`; `WPCPM_Institution_Members::institution_of()`, `level_of()`, `LEVEL_TUTOR`; `WPCPM_Institution_Agreement::is_settled()`.
- Produces:
  - `WPCPM_Sponsor_Event_List::shows( WP_User $viewer ): bool`: true exactly when `render( $viewer, $back )` would print its section (the same three tests `render()` makes before its first `printf`: the viewer is the current user, the list is live, and there is an event open to the viewer or a pending message). `render()` calls it, so the two cannot disagree; its output is unchanged (digest from `c0dfdf0a` for a viewer with two events, and for one with none).
  - `WPCPM_Institutions_Dashboard::views_for( string $record, WP_User $viewer, bool $can_manage ): array`, the view keys in menu order: `overview`; `students` when `ACT_VIEW_ROSTER` is allowed; `enroll` when `WPCPM_Institution_Import_Form::enabled()` and `ACT_ADD_STUDENT` is allowed; `reports` when `ACT_VIEW_SEMESTER_REPORT` is allowed; `institution` when `ACT_VIEW_ROSTER` is allowed; `agreement` always; `events` when the reader's own page (`(string) institution_of( $viewer ) === $record` and the level is not `LEVEL_TUTOR`), the agreement is settled, and `WPCPM_Sponsor_Event_List::shows( $viewer )`; `administrators` when `$can_manage`. No card is drawn to answer it (design 3).
  - `WPCPM_Institutions_Dashboard::choice_views(): array`: the views an Administrator with no school chosen is listed, without counts: `overview`, `students`, `enroll` (while `enabled()`), `reports`, `institution`, `agreement`, `administrators`.

- [ ] **Step 1: Write the failing checks** (stand-ins: the policy's `decide()` answering from a table keyed by viewer and action, the members' `institution_of()` and `level_of()`, `enabled()` from a global, `WPCPM_Sponsor_Event_List::shows()` from a global in this suite only):
  ```php
  $rep    = viewer( 'rep', $rec, 'representative' );   // settled school
  $tutor  = viewer( 'tutor', $rec, 'tutor' );
  $locked = viewer( 'locked', $rec_locked, 'representative' );
  $admin  = viewer( 'admin', '', '' );
  $GLOBALS['events_show'] = true; $GLOBALS['import_on'] = true;
  ck( 'a representative', WPCPM_Institutions_Dashboard::views_for( $rec, $rep, false ), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ) );
  // Review Focus 4.
  ck( 'a tutor: no Enroll students, no Events', WPCPM_Institutions_Dashboard::views_for( $rec, $tutor, false ), array( 'overview', 'students', 'reports', 'institution', 'agreement' ) );
  // Review Focus 3.
  ck( 'a locked school: Overview and Agreement', WPCPM_Institutions_Dashboard::views_for( $rec_locked, $locked, false ), array( 'overview', 'agreement' ) );
  ck( 'an Administrator viewing a school', WPCPM_Institutions_Dashboard::views_for( $rec, $admin, true ), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) );
  ck( 'an Administrator viewing a locked school', WPCPM_Institutions_Dashboard::views_for( $rec_locked, $admin, true ), array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) );
  $GLOBALS['events_show'] = false;
  ck( 'no event: no Events view', in_array( 'events', WPCPM_Institutions_Dashboard::views_for( $rec, $rep, false ), true ), false );
  $GLOBALS['import_on'] = false;
  ck( 'enrollment switched off: no Enroll students view', in_array( 'enroll', WPCPM_Institutions_Dashboard::views_for( $rec, $rep, false ), true ), false );
  ck( 'the choice lists, enrollment off', WPCPM_Institutions_Dashboard::choice_views(), array( 'overview', 'students', 'reports', 'institution', 'agreement', 'administrators' ) );
  ck( 'views_for draws no card', $GLOBALS['card_calls'], array() );
  ```
  In `bin/test-sponsor-event-list.php`: `shows()` is true for a viewer with an open event and false for another user than the current one, for a list that is not live, and for no event and no pending message; and the two `render()` digests are unchanged.
- [ ] **Step 2: Run** both suites. Expected: the new checks fail.
- [ ] **Step 3: Implement** `shows()` (moving `render()`'s three early returns into it, nothing else), `views_for()` and `choice_views()`.
- [ ] **Step 4: Run** both suites and the battery. Expected: all pass.
- [ ] **Step 5: Commit:** `Institution Dashboard: which views a reader may open, from the policy questions their cards ask` (paths: the dashboard class, the event list class, both suites).

### Task 4: What waits and what is news

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (new methods; `render_banner()` `:1249-1281` and `state_label()` `:1293-1315` read, the banner's two sentences moved into a method both call); `includes/modules/class-wpcpm-institution-panel.php` (`render_state()` `:985-1088`: the sentence for each state into `state_sentence()`); `includes/modules/class-wpcpm-institution-import-form.php` (`render_confirm()` `:612`, `render_progress()` `:656`: their lead sentence into `waiting_sentence()`); `includes/modules/class-wpcpm-institution-details-card.php` (`render_outcome()` `:381-448`: the sentence into `outcome_sentence()`).
- Test: `bin/test-institution-overview.php` (new); neighbors `bin/test-institution-panel.php`, `bin/test-institution-import-form.php`, `bin/test-institution-details-card.php`.

**Interfaces:**
- Consumes: `WPCPM_Institution_Agreement::summary( $record )` (`state`, `airtable_status`) and `SUMMARY_*` (`:132-138`); `WPCPM_Institution_Import::active_for( $record )` (`class-wpcpm-institution-import.php:604`); `WPCPM_Semester_Report::reports_of( $record )` (`:1001`), `state()`, `approved_at()` (`:706`), `cohort_of()`, `STATE_APPROVED`; `WPCPM_Cohort::label()`; `WPCPM_Institution_Request::latest_details_outcome( $record )` (`:969`, `state`, `closed_at`, `note`), `open_details_for( $record )` (`:874`), `STATE_DONE`, `STATE_DECLINED`; `views_for()` (Task 3).
- Produces:
  - `WPCPM_Institution_Panel::state_sentence( string $state, array $row = array() ): string`: the sentence `render_state()` prints for `none`, `generated` (with the generated day and template from `$row`), `returned` ("Upload the corrected agreement, signed, as a PDF.") and `revoked` ("Upload a new signed agreement as a PDF. An Administrator reviews it the same way as the first one."); '' for any other state. `render_state()` prints it where it printed the sentence; its output is pinned by digest for each state.
  - `WPCPM_Institution_Import_Form::waiting_sentence( int $batch_id ): string`: the sentence the form prints above a checked list not yet created, or above a creation in progress; `render_confirm()` and `render_progress()` print it; output pinned.
  - `WPCPM_Institution_Details_Card::outcome_sentence( array $outcome ): string`: the sentence `render_outcome()` prints for an approved request, and for a declined one with and without a note ("An Administrator did not make the change you asked for." / "An Administrator did not make the change you asked for: %s"); '' for a withdrawn one; output pinned.
  - `WPCPM_Institutions_Dashboard::banner_sentences( string $record ): array`: the two sentences `render_banner()` prints (the second only when Airtable records a status); `render_banner()` prints them, unchanged.
  - `WPCPM_Institutions_Dashboard::waiting_items( string $record, WP_User $viewer, bool $can_manage ): array`. Each item: `card` (a `VIEW_CARDS` key), `count` (int), `title`, `why`, `action` (the link's words), `tag` (`Waits` or `New`, translated), `tone` (`red` for Waits, '' for New), `args` (query arguments for `card_url()`), `news` (bool). Order: the waiting rows first, then the news. Only a row whose view is in `views_for()` is returned.
    1. An Administrator and the agreement not settled (ruling P1): title "The agreement is not settled" (new), why `implode( ' ', banner_sentences() )`, action "Open the agreement", card `agreement`, Waits, count 1. No school agreement row for this reader.
    2. A member, the agreement not settled and its state `none`, `generated`, `returned` or `revoked`: title "Collaboration Agreement not signed yet" (new) for `none` and `generated`, "Collaboration Agreement returned" and "Collaboration Agreement revoked" (exist); why `state_sentence( $state, $row )`; action "Open the agreement"; card `agreement`; Waits; count 1. A signed copy waiting for review (`submitted`) is no row (builder's call 7).
    3. `active_for( $record ) > 0`: title "A checked list of students waits to be created" (new), why `waiting_sentence( $batch )`, action "Open the list" (new), card `import`, Waits, count 1.
    4. News, each report whose state is approved and whose `approved_at()` is within 30 days (`30 * DAY_IN_SECONDS`) of `time()`, newest first: title `sprintf( __( 'Your semester report for %s is ready' ), WPCPM_Cohort::label( $cohort ) )` (new), why `sprintf( __( 'Approved on %s' ), <the day as the report index prints it> )` (exists), action "Read the report" (new), card `report`, args `array( 'wpcpm_report' => $cohort )`, New, `news` true.
    5. News, `latest_details_outcome()` in state done or declined, `closed_at` within 30 days, and `open_details_for( $record )` is 0: title "Your request was decided" (new), why `outcome_sentence( $outcome )`, action "See your details" (new), card `details`, New, `news` true.
  - `WPCPM_Institutions_Dashboard::waiting_by_view( array $items ): array<string,int>`: the sum of `count` per view, of the items whose `news` is false (ruling 4).

  - `private static function now(): int`: `(int) apply_filters( 'wpcpm_institution_now', time() )`, the one moment the 30-day window reads (PHP cannot stand in for `time()`, and `WPCPM_Dates` has no clock at `c0dfdf0a`); the suite's `apply_filters` stand-in returns its fixed `NOW` for that hook.
- [ ] **Step 1: Write the failing checks** in `bin/test-institution-overview.php` (stand-ins for the agreement summary, the import's `active_for()`, the reports, the latest outcome, and `apply_filters( 'wpcpm_institution_now', ... )` returning the suite's `NOW`):
  ```php
  $GLOBALS['summary'] = array( 'state' => 'returned', 'airtable_status' => '' );
  $items = WPCPM_Institutions_Dashboard::waiting_items( $rec_locked, $locked, false );
  ck( 'a returned agreement waits', array( $items[0]['title'], $items[0]['tag'], $items[0]['card'], $items[0]['action'] ), array( 'Collaboration Agreement returned', 'Waits', 'agreement', 'Open the agreement' ) );
  ck( 'its why is the panel\'s own sentence', $items[0]['why'], WPCPM_Institution_Panel::state_sentence( 'returned' ) );
  $GLOBALS['summary'] = array( 'state' => 'submitted', 'airtable_status' => '' );
  ck( 'a copy waiting for review is no row', WPCPM_Institutions_Dashboard::waiting_items( $rec_locked, $locked, false ), array() );
  $GLOBALS['summary'] = array( 'state' => 'none', 'airtable_status' => 'Awaiting review' );
  $a = WPCPM_Institutions_Dashboard::waiting_items( $rec_locked, $admin, true );
  ck( 'the Administrator sees one agreement row', count( array_filter( $a, static function ( $x ) { return 'agreement' === $x['card']; } ) ), 1 );
  ck( 'and it is the banner as a row', array( $a[0]['title'], $a[0]['why'] ), array( 'The agreement is not settled', implode( ' ', WPCPM_Institutions_Dashboard::banner_sentences( $rec_locked ) ) ) );
  // Ruling 4: news after waits, tagged New, not counted.
  $GLOBALS['summary'] = array( 'state' => 'accepted', 'airtable_status' => '' ); $GLOBALS['settled'] = true;
  $GLOBALS['active_batch'] = 7;
  $GLOBALS['reports'] = array( '2026-H1' => report( 'approved', NOW - 3 * DAY ), '2025-H2' => report( 'approved', NOW - 40 * DAY ) );
  $GLOBALS['outcome'] = array( 'state' => 'declined', 'closed_at' => NOW - 2 * DAY, 'note' => 'Use the full name.' ); $GLOBALS['open_details'] = 0;
  $i = WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false );
  ck( 'order and tags', array_map( static function ( $x ) { return $x['card'] . ':' . $x['tag']; }, $i ), array( 'import:Waits', 'report:New', 'details:New' ) );
  ck( 'the report row', array( $i[1]['title'], $i[1]['action'], $i[1]['args'] ), array( 'Your semester report for January to June 2026 is ready', 'Read the report', array( 'wpcpm_report' => '2026-H1' ) ) );
  ck( 'a report approved 40 days ago is no row', count( $i ), 3 );
  ck( 'the request row', array( $i[2]['title'], $i[2]['why'], $i[2]['action'] ), array( 'Your request was decided', 'An Administrator did not make the change you asked for: Use the full name.', 'See your details' ) );
  ck( 'only waits are counted', WPCPM_Institutions_Dashboard::waiting_by_view( $i ), array( 'enroll' => 1 ) );
  $GLOBALS['open_details'] = 31;
  ck( 'a newer request sent: no decided row', count( WPCPM_Institutions_Dashboard::waiting_items( $rec, $rep, false ) ), 2 );
  // Review Focus 4: a tutor never gets a row that opens Enroll students.
  ck( 'a tutor has no list row', array_column( WPCPM_Institutions_Dashboard::waiting_items( $rec, $tutor, false ), 'card' ), array( 'report' ) );
  ```
  In each neighbor suite: the new method's sentence for each state, and the card's whole output digest unchanged (taken from `c0dfdf0a`).
- [ ] **Step 2: Run** the four suites. Expected: the new checks fail, every old one passes.
- [ ] **Step 3: Implement** the three extractions (move the sentence, call the method, nothing else in the card), `banner_sentences()`, `waiting_items()`, `waiting_by_view()`.
- [ ] **Step 4: Run** the four suites and the battery. Expected: all pass (`suites=203`).
- [ ] **Step 5: Commit:** `Institution Dashboard: what waits for the school and what is news, from today's sentences` (paths: the four classes, the new suite, the three neighbor suites).

### Task 5: `render()` in the frame, a view at a time

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php`: `render()` `:314-534` (rewritten after its gate at `:342-406`, which stays as it is); `render_module()` `:685-709`, `render_module_body()` `:718-741` and the arrows' script enqueue `:463-465` stop being called; `handle_move()` `:622-667` and its hook at `:99` removed (builder's call 1; `ACTION_MOVE`, `OPT_MODULES_PREFIX`, `modules()`, `leads()` kept, P8 and uninstall); `render_help()` `:1029` stops being called from `render()` (the menu replaces it); new `frame_args()`, `render_head()`, `page_name()`, `render_view()`, `view()`.
- Test: `bin/test-institutions-dashboard.php` (rewritten where the page's shape changes), `bin/test-institution-views.php`.

**Interfaces:**
- Consumes: `WPCPM_Dashboard_Frame::open()`, `close()`, `item()`, `owns_page()`; `WPCPM_Dashboard_Card::unfold()`; `views_for()`, `waiting_items()`, `waiting_by_view()`, `view_url()`, `card_url()`, `keep_institution()`; `WPCPM_Updates::posts( 1, 'institution' )`; `WPCPM_Handbook_Assistant::guides()['institution']` (`url`, `slack`); `WPCPM_Institution_Assignment::contact_of()`.
- Produces:
  - `public static function view( string $record, WP_User $viewer, bool $can_manage ): string`: `asked_view()` when it is in `views_for()`, else `overview` (ruling P5).
  - `private static function frame_args( string $view, string $record, array $views, array $counts, bool $can_manage ): array`, what `WPCPM_Dashboard_Frame::open()` takes: `page` `institution` (`institution-guide` for `guide`, `institution-updates` for `updates`), `label` `title()`, one group with no heading holding, for each of `$views` in order, `item( <name>, <url>, <icon>, array( 'current' => $key === $view, 'badge' => <"%s waits" / "%s wait" with the count, '' for 0>, 'tone' => 'red' ) )`, the Overview's count the sum (the Sponsor's `_n( '%s waits', '%s wait', ... )`, existing string); then `Program updates` (`inbox`) while `WPCPM_Updates::posts( 1, 'institution' )` has one, its permalink through `keep_institution()`; `help`: "Institution guide" (`book`) to the guide's `url` (through `keep_institution()` when it is this site's own published `institution-guide` page), "#wpcredits on Slack" (`slack`, the guide entry's `slack` address; the Administrator Dashboard's existing string), "Write to the program" (`email`, `mailto:` the assigned Administrator's address, only while one is assigned with an address). The Ask box is the frame's own (`ask()`), unchanged.
    Names and icons: Overview `home`, Students `users`, Enroll students `profile`, Semester reports `chart`, Your institution `building`, Agreement `file`, Events `calendar`, For Administrators `eye` (design 15). The Students item, while the address names a student (builder's call 3), keeps the reader's roster filters: `view_url( 'students', $managed, array( wpcpm_cohort, wpcpm_roster_status, wpcpm_roster_search from the request ) )`. Every url through `view_url( $key, $managed )` where `$managed` is `$record` for an Administrator and '' for a member.
  - `private static function page_name(): string`: `<h1 class="wpcpm-head__eyebrow">Institution Dashboard</h1>` when `owns_page( 'institution' )`, else ''.
  - `private static function render_head( string $title, string $lede, string $actions = '', string $media = '', string $facts = '' ): void`: `<header class="wpcpm-head{ wpcpm-head--logo when media}">{media}<div class="wpcpm-head__copy">{page_name}<h2 class="wpcpm-head__title">{title}</h2>{<p class="wpcpm-head__lede">lede</p>}{facts}{actions}</div></header>`.
  - `private static function render_view( string $view, string $record, array $context, WP_User $viewer ): void`: the view's head (title and lede of design 12), then its cards in order, inside `WPCPM_Dashboard_Card::unfold( true )` / `unfold( false )`, buffered; when the cards print nothing, `<p class="wpcpm-overview__quiet">Nothing to show here yet.</p>` (existing string). Cards per view, through the existing `card()` (`:756`):
    - `students`: `WPCPM_Institution_Roster_View` (context with `module_titled` true), then `WPCPM_Institution_Roster_View::read_line( $context['read'], 'wpcpm-dashboard__updated' )` once at the roster's foot.
    - `enroll`: `WPCPM_Institution_Import_Form`. `reports`: `WPCPM_Semester_Report_Screen`. `institution`: `WPCPM_Institution_Details_Card`, `WPCPM_Institution_People`. `agreement`: for an Administrator while not settled `render_banner()` first, then `WPCPM_Institution_Panel`, then `WPCPM_Institution_Agreement_Card`. `events`: `WPCPM_Sponsor_Event_List::render( $viewer, BACK_INSTITUTION )`. `administrators`: `WPCPM_Institution_History::render( $record )`.
    - Titles and ledes: Students "Students" / "Your students on the program, by cohort and by where they are, with the list you can download." (new); Enroll students "Enroll students" / "Add students to the program, one at a time or from a list." (new); Semester reports "Semester reports" (new) / today's module lede (`leads()['report']`); Your institution "Your institution" / "What the program has on record about your institution, and who can see this page." (new); Agreement "Collaboration Agreement" / "The agreement between your institution and the program, and the signed copy on file." (new); Events "Events" / "Events the program's sponsors host, to join with your colleagues." (new); For Administrators "For Administrators" / "Only Administrators see this section." (today's).
  - `render()` after the gate: `$view = self::view(...)`; `WPCPM_UI::enter( 'institution' )`; `WPCPM_Dashboard_Frame::open( frame_args(...) )`; the root `<div class="wpcpm-dashboard wpcpm-institution wpcpm-institution--{view}{root_class}">`; `WPCPM_Two_Factor::prompt()`; the shortcode title as today; today's switcher for an Administrator (Task 7 adds `keep`); the Overview (Task 6, a placeholder `render_overview()` printing the head and nothing else in this task) or `render_view()`; `</div>`; `WPCPM_Dashboard_Frame::close()`; `leave()` in `finally`, as now.
- [ ] **Step 1: Write the failing checks** in `bin/test-institutions-dashboard.php` (the page as a representative of a settled school, as a tutor, as a locked representative, as an Administrator viewing a school; the stand-in cards record their calls in `$GLOBALS['card_calls']`):
  ```php
  $html = page_as( $rep, array() );
  ck( 'the page is in the frame', substr_count( $html, 'class="wpcpm-frame wpcpm-frame--institution' ), 1 );
  ck( 'menu items, in order', menu_labels( $html ), array( 'Overview', 'Students', 'Enroll students', 'Semester reports', 'Your institution', 'Agreement', 'Events' ) );
  ck( 'no arrows', array( strpos( $html, 'Move Students up' ), strpos( $html, WPCPM_Institutions_Dashboard::ACTION_MOVE ) ), array( false, false ) );
  ck( 'the move handler is gone', has_hook_call( 'admin_post_wpcpm_institution_module_move' ), false );
  ck( 'the Overview calls no card', $GLOBALS['card_calls'], array() );
  $html = page_as( $rep, array( 'wpcpm_view' => 'students' ) );
  ck( 'Students draws the roster', $GLOBALS['card_calls'], array( 'WPCPM_Institution_Roster_View' ) );
  ck( 'the read line once, at its foot', substr_count( $html, 'wpcpm-dashboard__updated' ), 1 );
  ck( 'one H2, the view title', h2s( $html ), array( 'Students' ) );
  page_as( $rep, array( 'wpcpm_view' => 'institution' ) );
  ck( 'Your institution', $GLOBALS['card_calls'], array( 'WPCPM_Institution_Details_Card', 'WPCPM_Institution_People' ) );
  // Review Focus 3.
  foreach ( array( array( 'wpcpm_view' => 'students' ), array( 'wpcpm_view' => 'enroll' ), array( 'wpcpm_institution_student' => '12' ), array( 'wpcpm_report' => '2026-H1' ) ) as $q ) {
  	$html = page_as( $locked, $q );
  	ck( 'locked, ' . http_build_query( $q ) . ': the Overview, no card', array( current_item( $html ), $GLOBALS['card_calls'] ), array( 'Overview', array() ) );
  }
  ck( 'a locked menu', menu_labels( page_as( $locked, array() ) ), array( 'Overview', 'Agreement' ) );
  // Review Focus 4.
  foreach ( array( 'enroll', 'events' ) as $v ) {
  	ck( "a tutor asking for $v gets the Overview", current_item( page_as( $tutor, array( 'wpcpm_view' => $v ) ) ), 'Overview' );
  }
  // Review Focus 5.
  set_flash( WPCPM_Institution_Import_Form::FLASH, array( 'status' => 'checked' ) );
  set_flash( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'sent' ) );
  page_as( $rep, array() );
  ck( 'the Overview leaves the cards\' flashes', array( has_flash( WPCPM_Institution_Import_Form::FLASH ), has_flash( WPCPM_Institution_Request::FLASH_DETAILS ) ), array( true, true ) );
  ck( 'counts on the menu', menu_badges( page_as_with_waiting( $rep ) ), array( 'Overview' => '2 wait', 'Enroll students' => '1 waits', 'Agreement' => '1 waits' ) );
  ck( 'Help', help_labels( page_as( $rep, array() ) ), array( 'Institution guide', '#wpcredits on Slack', 'Write to the program' ) );
  ck( 'Program updates while one exists', in_array( 'Program updates', menu_labels( page_as_with_update( $rep ) ), true ), true );
  ck( 'an Administrator\'s links keep the school', all_menu_urls_hold( page_as( $admin, array( 'wpcpm_institution_view' => $rec ) ), 'wpcpm_institution_view=' . $rec ), true );
  ck( 'a member\'s links name none', any_menu_url_holds( page_as( $rep, array() ), 'wpcpm_institution_view' ), false );
  ck( 'an empty view says so', strpos( page_as_no_cards( $rep, 'reports' ), 'Nothing to show here yet.' ) !== false, true );
  ck( 'the Students item keeps the filters on a card', menu_url( page_as( $rep, array( 'wpcpm_view' => 'students', 'wpcpm_institution_student' => '12', 'wpcpm_roster_status' => 'waiting' ) ), 'Students' ), $page . '?wpcpm_view=students&wpcpm_roster_status=waiting' );
  ```
  (The counts are the Sponsor Dashboard's own words: `_n( '%s waits', '%s wait', $n )`, so one reads "1 waits" and two "2 wait".) The old checks of the page's module order, arrows and resources are removed, each listed in the report; every check of what a card is handed (`$context`), the gate's refusals and the two-factor prompt stays.
  Register with the layout harness: the Overview head and menu for a representative and an Administrator with the 40-character school name, and the Students view's head.
- [ ] **Step 2: Run** both suites. Expected: the new checks fail.
- [ ] **Step 3: Implement.** Keep the gate at `:342-406` byte for byte. Run `php bin/test-guide-labels.php`; keep any string it needs (P8).
- [ ] **Step 4: Run** both suites, the layout harness (`0 faults`), the battery, the standards and the static analysis. Expected: all pass.
- [ ] **Step 5: Commit:** `Institution Dashboard: in the dashboards' frame, a view per menu item, no arrows; the cards in their views as they are` (paths: the class, both suites).

### Task 6: The Overview

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`render_overview()`; `render_identity()` `:1119-1210` and `contact_block()` `:1048-1101` no longer called by the page: their facts and words move into the head and the contact card; `site_icon()` `:850` used as it is); `includes/modules/class-wpcpm-institution-roster-view.php` (`default_cohort()` `:349` reached through a new public `opening_cohort()`, which `render()` `:126-128` uses).
- Test: `bin/test-institution-overview.php`; `bin/test-institution-roster-view.php`.

**Interfaces:**
- Consumes: `profile()` (`:789`), `site_icon()`, `website_url()` (`:1223`); `WPCPM_UI::breakable()`, `item_header()` facts style, `list_open()`, `row()`, `list_close()`, `tag()`, `button()`, `heading()`, `intro()`; `WPCPM_Cohort::participation( $rows, $cohort )` (`signed_up`, `graduated`), `WPCPM_Roster_Index::rows()`, `groups( $record, $cohort )`, `read()`; `waiting_items()`, `card_url()`.
- Produces:
  - `WPCPM_Institution_Roster_View::opening_cohort( string $record ): string`: the cohort the Students view opens on with no `wpcpm_cohort` in the address (`default_cohort( cohort_counts( rows ) )`); `render()` uses it, output unchanged.
  - `private static function render_overview( string $record, array $profile, array $context, WP_User $viewer, bool $can_manage, array $views ): void`, in this order:
    1. The head (`render_head()`): media the 48px site icon as today (`<img class="wpcpm-institution__icon" ... width="48" height="48" ... onerror="this.remove()">`, builder's call 10) when there is one; title `WPCPM_UI::breakable( $name )` (the name, or "Unnamed institution"); no lede; facts one line `<p class="wpcpm-head__facts">` holding each fact in `<span class="wpcpm-ui-item__fact">`: city and country (`, ` between them, as today), the website as a link (today's `rel="external noopener"`, the address without its scheme), "Contact: %s", and for an Administrator "Stage: %s" (today's strings; a missing fact takes no separator); actions "Enroll students" (`WPCPM_UI::button( kind secondary, href card_url( 'import', $managed ) )`) while `enroll` is in `$views` (P3).
    2. The figures, while the reader may open `students`: for `opening_cohort()`: `signed_up` "signed up", `graduated` "graduated", the count of the `current` group "on the program now", the count of the `waiting` group "waiting for a mentor" (new labels), each `<div class="wpcpm-figure">` with value, label and the base `sprintf( __( 'in %s' ), WPCPM_Cohort::label( $cohort ) )` (new); a cell at zero is left out; with all four at zero no band. The band is `<section class="wpcpm-figures wpcpm-figures--{n}" aria-label="...">` (the Sponsor's markup, label `__( 'Your students at a glance' )`, new). Under it the roster's read line once (`read_line( $context['read'], 'wpcpm-dashboard__updated' )`).
    3. What waits: `waiting_items()`; none: `<p class="wpcpm-overview__quiet">Nothing waits for you.</p>`; else `<section class="wpcpm-institution__waiting" aria-labelledby="wpcpm-institution-overview-waiting">` with `WPCPM_UI::heading( 'Waiting for you', 'h2', 'wpcpm-institution-overview-waiting' )` and a list of `WPCPM_UI::row()`s: `tag` (Waits red, New neutral), `name` the title, `facts` the why, `actions` one `button( kind secondary, href card_url( card, $managed, '', args ), label action )`.
    4. Your contact at the program, while `contact_of()` names somebody: `<section class="wpcpm-institution__contact">` with `WPCPM_UI::heading( 'Your contact at the program', 'h2' )`, the name, the address as a `mailto:` link, "Book a call" (today's `target="_blank" rel="noopener noreferrer"`) when there is a booking link, and the sentence "They look after your institution. Write to them about anything the guide does not answer." (today's).
- [ ] **Step 1: Write the failing checks** in `bin/test-institution-overview.php` (the page drawn through `render()` for each reader):
  ```php
  $html = overview_as( $rep, school( array( 'name' => 'Exampleinstituteforappliedwebengineerings of Example', 'city' => 'Exampleton', 'country_name' => 'Exampleland', 'website' => 'https://www.example.org/', 'contact_person' => 'Robin Example', 'stage' => 'Confirmed' ) ) );
  ck( 'the H2 is the school, breakable', h2s_html( $html ), array( WPCPM_UI::breakable( 'Exampleinstituteforappliedwebengineerings of Example' ) ) );
  ck( 'the facts, each its own', facts( $html ), array( 'Exampleton, Exampleland', 'www.example.org', 'Contact: Robin Example' ) );
  ck( 'a member never sees the stage', strpos( $html, 'Stage:' ), false );
  ck( 'an Administrator sees it last', array_slice( facts( overview_as( $admin, $school ) ), -1 ), array( 'Stage: Confirmed' ) );
  ck( 'no city: no separator', facts( overview_as( $rep, school( array( 'city' => '', 'country_name' => '' ) ) ) )[0], 'www.example.org' );
  ck( 'the icon at 48px', (bool) preg_match( '/<img class="wpcpm-institution__icon" src="[^"]+" alt="" width="48" height="48"/', $html ), true );
  // Review Focus 4.
  ck( 'Enroll students in the head', head_buttons( $html ), array( 'Enroll students' ) );
  ck( 'a tutor\'s head has none', head_buttons( overview_as( $tutor, $school ) ), array() );
  ck( 'a locked head has none', head_buttons( overview_as( $locked, $school ) ), array() );
  ck( 'figures', figure_labels( $html ), array( 'signed up', 'graduated', 'on the program now', 'waiting for a mentor' ) );
  ck( 'the base names the cohort', figure_bases( $html )[0], 'in January to June 2026' );
  ck( 'a zero is left out', figure_labels( overview_with_counts( 3, 0, 2, 1 ) ), array( 'signed up', 'on the program now', 'waiting for a mentor' ) );
  ck( 'all zero: no band', strpos( overview_with_counts( 0, 0, 0, 0 ), 'wpcpm-figures' ), false );
  ck( 'no figures for a locked school', strpos( overview_as( $locked, $school ), 'wpcpm-figures' ), false );
  ck( 'the read line once', substr_count( $html, 'Read from the program records on' ), 1 );
  ck( 'nothing waits', strpos( overview_nothing( $rep ), 'Nothing waits for you.' ) !== false, true );
  ck( 'a row: tag, title, why, one secondary', row_parts( overview_with_rows( $rep ), 0 ), array( 'Waits', 'A checked list of students waits to be created', WPCPM_Institution_Import_Form::waiting_sentence( 7 ), 'Open the list' ) );
  ck( 'a news row is neutral', tag_classes( overview_with_rows( $rep ), 1 ), 'wpcpm-status' );
  ck( 'the row opens its card', row_href( overview_with_rows( $admin_viewing ), 0 ), $page . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec . '#wpcpm-import' );
  ck( 'the contact card', contact_parts( $html ), array( 'Your contact at the program', 'Alex Example', 'mailto:alex.example@example.org', 'Book a call', 'They look after your institution. Write to them about anything the guide does not answer.' ) );
  ck( 'no contact: no card', strpos( overview_no_contact( $rep ), 'Your contact at the program' ), false );
  ck( 'no Resources block', strpos( $html, 'wpcpm-resources' ), false );
  ```
  In `bin/test-institution-roster-view.php`: `opening_cohort()` is this semester when it has students and else the newest cohort; the roster's output unchanged (digest).
  Register with the layout harness: the Overview for a representative with the 40-character school name, three waiting rows and two news rows, the contact card with a 48-character address, and for an Administrator with the stage.
- [ ] **Step 2: Run.** Expected: the new checks fail.
- [ ] **Step 3: Implement.** `render_identity()` and `contact_block()` are deleted only if `grep -rn "render_identity\|contact_block" includes/ bin/` finds no other caller; their strings are reused, not retyped.
- [ ] **Step 4: Run** the suites, the layout harness at the eight widths (`0 faults`), the battery, the standards and the static analysis.
- [ ] **Step 5: Commit:** `Institution Dashboard: the Overview, the school's head, its figures, what waits and what is new, and its contact` (paths: the two classes, the two suites).

### Task 7: Viewing as institution carried, and the choice state

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`render()` before `resolve_institution()` at `:352`; `render_switcher()` `:1329-1343`).
- Test: `bin/test-institutions-dashboard.php`; neighbor `bin/test-institution-roster.php` (unchanged: `resolve_institution()` and its fallback are left as they are).

**Interfaces:**
- Consumes: `requested_record()`, `choice_views()`, `view_url()`, `WPCPM_Dashboards::render_switcher()` with `keep`.
- Produces:
  - In `render()`, before the record is resolved: when `current_user_can( CAP_MANAGE )` and `'' === requested_record()` and `'' === (string) WPCPM_Institution_Members::institution_of( $viewer )` (design 7, ruling 3), draw the choice state and return: the frame with `frame_args()` over `choice_views()` with no counts and no current item but the Overview, each item `view_url( $key )` with no school; the head: page name, title "Choose an institution" (exists), lede "Pick the school whose page you want to see. Only Administrators see this." (new); then `render_switcher( '', $asked_view )`. Nothing is stored (ruling 3).
  - `render_switcher( string $current, string $view = self::VIEW_OVERVIEW )`: today's call plus `'keep' => self::VIEW_OVERVIEW !== $view ? array( self::VIEW_ARG => $view ) : array()`, so choosing a school keeps the view (the Sponsor's at `class-wpcpm-sponsors-dashboard.php:1610-1623`). On the choice state `$current` is '' and the select's first option is the existing placeholder.
- [ ] **Step 1: Write the failing checks:**
  ```php
  $html = page_as( $admin, array() );
  ck( 'no school chosen: the choice', h2s( $html ), array( 'Choose an institution' ) );
  ck( 'its lede', lede( $html ), 'Pick the school whose page you want to see. Only Administrators see this.' );
  ck( 'the first school is not drawn', $GLOBALS['card_calls'], array() );
  ck( 'resolve_institution is not asked', $GLOBALS['resolve_calls'], 0 );
  ck( 'the menu, no counts', array( menu_labels( $html ), menu_badges( $html ) ), array( array( 'Overview', 'Students', 'Enroll students', 'Semester reports', 'Your institution', 'Agreement', 'For Administrators' ), array() ) );
  ck( 'each lands on the choice', menu_urls_name_no_school( $html ), true );
  ck( 'the switcher keeps the view', hidden_inputs( page_as( $admin, array( 'wpcpm_view' => 'reports' ) ), 'wpcpm-institution-switcher' ), array( 'wpcpm_view' => 'reports' ) );
  ck( 'nothing stored', $GLOBALS['update_option_calls'], array() );
  ck( 'a member is unchanged', h2s( page_as( $rep, array() ) )[0] !== 'Choose an institution', true );
  ck( 'an Administrator with a school', $GLOBALS['resolve_calls'] > 0 && 'Choose an institution' !== h2s( page_as( $admin, array( 'wpcpm_institution_view' => $rec ) ) )[0], true );
  ```
- [ ] **Step 2: Run.** Expected: the new checks fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the suite, `bin/test-institution-roster.php`, `bin/test-sponsors-dashboard.php` (untouched), the layout harness (the choice state at the eight widths), the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: an Administrator with no school chosen is asked to choose one; Viewing as keeps the view` (paths: the class, the suite).

### Task 8: The menu for the guide and the updates

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (new `menu_args()`).
- Test: `bin/test-institution-views.php`.

**Interfaces:**
- Consumes: `frame_args()`, `views_for()`, `choice_views()`, `waiting_items()`, `waiting_by_view()`, `requested_record()`.
- Produces: `public static function menu_args( string $view ): array`: `$view` `guide` or `updates` (anything else is `guide`). The record is `requested_record()` for an Administrator and `institution_of( current user )` for a member; with a record, `frame_args( $view, $record, views_for(...), waiting_by_view( waiting_items(...) ), $manage )`; an Administrator with none gets `choice_views()` without counts (the Sponsor's `menu_args()` rule). The `page` is `institution-guide` or `institution-updates`; the guide item or the Program updates item is current.
- [ ] **Step 1: Write the failing checks:** `menu_args( 'guide' )['page']` is `institution-guide`; for a representative its items are the representative's views with their counts and the Institution guide item is current; for an Administrator whose address names `$rec`, every item's url holds `wpcpm_institution_view=$rec`; for an Administrator with no school, the items are `choice_views()` with no badge; `menu_args( 'updates' )['page']` is `institution-updates`; `menu_args( 'bogus' )['page']` is `institution-guide`.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the suite and the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: the menu its guide and its updates are drawn with` (paths: the class, the suite).

### Task 9: Every way back, and every link to a card

**Files (each way back through `card_url()`, the design 9 list):**
- `includes/modules/class-wpcpm-institution-import-form.php` `leave()` (`:1470-1491`: `page_url()` at `:1476`, `#wpcpm-import` at `:1490`) to `card_url( 'import', $view_record, '', $batch ? array( ARG_BATCH => $batch ) : array() )`.
- `includes/modules/class-wpcpm-institution-details-card.php` `finish()` (`:1010-1032`) to `card_url( 'details', $manage ? $record : '', $anchor )`.
- `includes/modules/class-wpcpm-institution-request.php` `bounce_details()` (`:2283-2296`): the referer as today; its fallback `dashboard_url()` replaced by `WPCPM_Institutions_Dashboard::card_url( 'details' )`.
- `includes/modules/class-wpcpm-institution-invite.php` `finish()` (`:2170-2210`) to `card_url( 'people', $manage ? $record : '' )`; the `RETURN_ADMIN` branch to Manage members unchanged; `dashboard_url()` (`:2216-2221`) left for the accept redirect at `:858` (the Overview, as today).
- `includes/modules/class-wpcpm-institution-people.php` `card_url( $record )` (`:1451-1459`) returns `WPCPM_Institutions_Dashboard::view_url( 'institution', $manage ? $record : '' )` (no anchor: its callers `finish()` `:1438` and `WPCPM_Institution_Tutors::finish()` `:619` append theirs, unchanged).
- `includes/modules/class-wpcpm-semester-report-screen.php` `report_url( $cohort )` (`:318-326`) to `card_url( 'report', '', '', array( self::ARG => $cohort ) )`; `leave()` (about `:3603-3621`) its fallback `page_url() . '#wpcpm-report'` to `card_url( 'report' )`; `render_reading()` (`:597`, the back link `:598-605`) and `render_editor()` (`:827`, the back link) print the back link only when `WPCPM_UI::surface() !== 'institution'` (the pre-flight's ruling: the wp-admin tab keeps it).
- `includes/modules/class-wpcpm-institution-student-view.php` `render_back()` (`:628`): not printed while `WPCPM_UI::surface() === 'institution'` (builder's call 3).
- `includes/modules/class-wpcpm-institution-student-form.php` `:1740`, `class-wpcpm-institution-students.php` `:1005`, `class-wpcpm-institution-resend.php` `:551`: `WPCPM_Request::back()` as today, its fallback `page_url()` replaced by `card_url( <the class's ANCHOR without wpcpm-> )`, the anchor appended as today.
- `includes/modules/class-wpcpm-institution-assignment.php` `page_url( $record_id )` (`:284-290`) to `card_url( 'details', trim( $record_id ) )`, home when the page has no address.
- `includes/modules/class-wpcpm-sponsor-event-list.php` `page_of()` (`:725-747`): `BACK_INSTITUTION` to `card_url( 'sevents' )` (the other three keys unchanged).
- `includes/modules/class-wpcpm-agreement-generate.php` `bounce()` (`:1055-1066`): the referer as today, its fallback to `card_url( 'agreement' )`.
- Links from elsewhere that name a card: `includes/modules/class-wpcpm-administrators-cards.php` `report_link()` (about `:4830-4834`, through `report_url()` and the school); `includes/modules/class-wpcpm-institutions.php` the link to `#wpcpm-agreement` (`:4644-4656`) to `card_url( 'agreement', $record_id )`. `institution_href()` (`:4813`), `WPCPM_Institutions_Table::dashboard()` (`:1199`), `WPCPM_Dashboards::links()` (`:79`) and the accepted and reinstated mails (`class-wpcpm-institution-agreement.php:3849`, `:3986`) link the bare page: the Overview, unchanged (design 9).
- Test: `bin/test-institution-ways-back.php` (new); neighbors `bin/test-institution-import-form.php`, `bin/test-institution-details-card.php`, `bin/test-institution-request.php`, `bin/test-institution-invite.php`, `bin/test-institution-people.php`, `bin/test-institution-tutors.php`, `bin/test-semester-report.php`, `bin/test-institution-student-view.php`, `bin/test-institution-student-form.php`, `bin/test-institution-resend.php`, `bin/test-institution-assignment.php`, `bin/test-sponsor-event-list.php`, `bin/test-institutions-screen.php`.

**Interfaces:**
- Consumes: `WPCPM_Institutions_Dashboard::card_url()`, `view_url()`, `view_of()` (Task 2).
- Produces: no new method. Every listed URL, for an Administrator viewing `$rec` and for a member, with its view and anchor.

- [ ] **Step 1: Write the failing checks** in `bin/test-institution-ways-back.php`: drive each handler's exit path through its own suite's stand-ins (the redirect stand-in records the URL; `wp_get_referer()` returns false to take the fallback, and a referer naming a view to take that), one row each, as an Administrator viewing `$rec` and as a member. The table the suite holds (Review Focus 2 and 5):
  ```php
  $rows = array(
  	// handler exit                                  view            anchor                     flash the card takes
  	array( 'import leave',                          'enroll',       '#wpcpm-import',           WPCPM_Institution_Import_Form::FLASH ),
  	array( 'import leave with a batch',             'enroll',       '#wpcpm-import',           WPCPM_Institution_Import_Form::FLASH ),
  	array( 'details finish',                        'institution',  '#wpcpm-details',          WPCPM_Institution_Request::FLASH_DETAILS ),
  	array( 'details request bounce, no referer',    'institution',  '#wpcpm-details',          WPCPM_Institution_Request::FLASH_DETAILS ),
  	array( 'invite finish',                         'institution',  '#wpcpm-people',           WPCPM_Institution_Invite::FLASH ),
  	array( 'people finish',                         'institution',  '#wpcpm-people',           WPCPM_Institution_People::FLASH ),
  	array( 'tutors finish',                         'institution',  '#wpcpm-tutors',           WPCPM_Institution_Tutors::FLASH ),
  	array( 'semester report leave',                 'reports',      '#wpcpm-report',           '' ),
  	array( 'student form, no referer',              'students',     '#wpcpm-student-form',     '' ),
  	array( 'student status, no referer',            'students',     '#wpcpm-student-status',   '' ),
  	array( 'resend, no referer',                    'students',     '#wpcpm-student-invite',   '' ),
  	array( 'sponsor events, institution',           'events',       '#wpcpm-sevents',          '' ),
  	array( 'agreement generate bounce, no referer', 'agreement',    '#wpcpm-agreement',        '' ),
  );
  foreach ( $rows as $r ) {
  	$as_admin  = exit_url( $r[0], 'admin', $rec );
  	$as_member = exit_url( $r[0], 'member', $rec );
  	ck( "$r[0], Administrator: view and school", array( q( $as_admin, 'wpcpm_view' ), q( $as_admin, 'wpcpm_institution_view' ), frag( $as_admin ) ), array( $r[1], $rec, $r[2] ) );
  	ck( "$r[0], member: view and no school", array( q( $as_member, 'wpcpm_view' ), q( $as_member, 'wpcpm_institution_view' ), frag( $as_member ) ), array( $r[1], null, $r[2] ) );
  	if ( '' !== $r[3] ) {
  		ck( "$r[0]: its flash is taken on that view", WPCPM_Institutions_Dashboard::view_of( card_taking( $r[3] ) ), $r[1] );
  	}
  }
  ck( 'a batch travels', q( exit_url( 'import leave with a batch', 'member', $rec ), 'wpcpm_batch' ), '7' );
  ck( 'a referer naming a view is kept', exit_url_with_referer( 'student form', $page . '?wpcpm_view=students&wpcpm_institution_student=12' ), $page . '?wpcpm_view=students&wpcpm_institution_student=12#wpcpm-student-form' );
  // Review Focus 1: links from elsewhere and mails.
  ck( 'the assignment mail opens the details card', WPCPM_Institution_Assignment::page_url( $rec ), $page . '?wpcpm_view=institution&wpcpm_institution_view=' . $rec . '#wpcpm-details' );
  ck( 'a report link', WPCPM_Semester_Report_Screen::report_url( '2026-H1' ), $page . '?wpcpm_view=reports&wpcpm_report=2026-H1#wpcpm-report' );
  ck( 'the accepted mail links the bare page', accepted_mail_link( $rec ), $page );
  ck( 'no back link on the dashboard', strpos( student_card_on( 'institution' ), 'Back to the roster' ), false );
  ck( 'the wp-admin tab keeps its back link', strpos( report_editor_on( 'wpcpm-institutions/reports' ), 'Back to the other semesters' ) !== false, true );
  ```
  `card_taking()` is the suite's own map from a flash channel to the card key whose render takes it (import to `import`, details to `details`, invite and people to `people`, tutors to `tutors`), read from each class's render, not invented. In each neighbor suite, every existing check of a redirect is updated to the new URL and listed in the report; nothing else in them changes.
- [ ] **Step 2: Run** the new suite and the neighbors. Expected: the new checks fail.
- [ ] **Step 3: Implement** each change in the list above, nothing else in the handlers.
- [ ] **Step 4: Run** the new suite, every neighbor and the battery (`suites=204`), the standards and the static analysis.
- [ ] **Step 5: Commit:** `Institution Dashboard: every way back and every link to a card opens the view that holds it, with the Administrator's school kept` (paths: every file listed, the new suite, the neighbor suites changed).

### Task 10: Anchor-only old links

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`render_overview()` prints the map); `assets/js/dashboard-open.js` (`openFragment()` `:55-89`, the move before it).
- Create: `bin/js/institution-anchors.js` (harness), `bin/test-institution-anchors.php` (runs it as `bin/test-dashboard-card.php` runs `bin/js/dashboard-open.js`; skips with a `skip` line when node is missing).

**Interfaces:**
- Produces, on the Overview only: `<script type="application/json" class="wpcpm-anchor-views">{"wpcpm-import":"<view_url( 'enroll', $managed )>", ...}</script>`: every anchor of every card of every view in the reader's `views_for()` but the Overview (`'wpcpm-' . $card` for each `VIEW_CARDS` key), escaped with `wp_json_encode()` and `JSON_HEX_TAG | JSON_HEX_AMP`. Builder's call 5: only this page prints it.
- Produces, in `dashboard-open.js`: on load, when the fragment names no element on the page, the script reads the first `script.wpcpm-anchor-views`, and when the map holds the fragment's id, `window.location.replace( map[ id ] + '#' + id )`; nothing else changes (with no map, or a fragment on the page, the old path runs).
- [ ] **Step 1: Write the failing checks:** in `bin/test-institution-anchors.php`, the Overview's map for a representative holds `wpcpm-import`, `wpcpm-report`, `wpcpm-details`, `wpcpm-people`, `wpcpm-tutors`, `wpcpm-student-form`, `wpcpm-agreement`, `wpcpm-sevents`, each with its view's address, and no `wpcpm-for-administrators`; a tutor's holds no `wpcpm-import` and no `wpcpm-sevents`; an Administrator's addresses hold the school; no other view prints the map; the Sponsor and Administrator Dashboards print none (their suites pass untouched). The harness (a stand-in page and `location`): `#wpcpm-import` with the map and no such element calls `replace( '.../?wpcpm_view=enroll#wpcpm-import' )`; `#wpcpm-import` with the element on the page calls no `replace` and opens it as before; with no map, no `replace`; a mutation proof: the script without the "names no element" test must make the second scenario fail.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the new suite, `bin/test-dashboard-card.php` (untouched), `bin/test-sponsors-dashboard.php`, the battery (`suites=205`).
- [ ] **Step 5: Commit:** `Institution Dashboard: an old link that names only an anchor moves to the view that holds it` (paths: the class, the script, the harness, the suite).

### Task 11: The Institution guide and Program updates in the frame

**Files:**
- Modify: `includes/modules/class-wpcpm-administrator-guide-view.php` (`guides()` `:87-103`, `may_read()` `:163-173`, `render()` the surface at `:240`); `includes/modules/class-wpcpm-administrator-updates-view.php` (`audience_of()` `:79-98`, `render()` `:147-160`, `others()` `:199-232`).
- Test: `bin/test-institution-frame-pages.php` (new); `bin/test-administrator-guide.php` and `bin/test-administrator-updates.php` pass untouched.

**Interfaces:**
- Consumes: `WPCPM_Institutions_Dashboard::menu_args()` (Task 8), `requested_record()`, `keep_institution()`, `FRAME_PAGE`; `WPCPM_Institution_Members::is_member()`; `WPCPM_Institutions_Dashboard::is_member()` (`:189`); `WPCPM_Updates::lists( $post, 'institution' )`, `posts( $n, 'institution' )`.
- Produces:
  - `guides()` gains `WPCPM_Handbook_Assistant::INSTITUTION_GUIDE_PATH` (`institution-guide`) => `frame` `institution-guide`, `dashboard` `WPCPM_Institutions_Dashboard`, `reader` `institution`, `title` `__( 'Institution guide' )` (exists).
  - `may_read( 'institution' )`: an Administrator, or a representative or tutor of any school (`WPCPM_Institutions_Dashboard::is_member()`, or a tutor by `WPCPM_Institution_Members::level_of()`; name the exact call in the report after reading `is_member()`).
  - `render()` names the surface of the guide's dashboard: `sponsor` for the Sponsor guide, `institution` for the Institution guide, `administrator` otherwise. Its "On this page" column is the same code path the other two use.
  - `audience_of()` gains `institution`: `owns_page( 'institution-updates' )` and `WPCPM_Updates::lists( $post, 'institution' )`; a member of a school reads it there; an Administrator does when the address names a school (`requested_record()`) or the Administrator Dashboard does not list it (the Sponsor's 1.123.79 rule, sponsor checked first so its behavior is unchanged).
  - `render( $content, $post, 'institution' )`: the menu `WPCPM_Institutions_Dashboard::menu_args( 'updates' )`, surface `institution`, the others' list from `posts( LIST_MAX, 'institution' )` with each link through `keep_institution( ..., requested_record() )`.
- [ ] **Step 1: Write the failing checks:** `frame_for()` of the `institution-guide` page is `institution-guide` for a representative, a tutor and an Administrator when the theme names it, '' for a sponsor's member and for a logged-out reader, '' when the theme does not name it; `render()` of it draws `wpcpm-frame--institution-guide` with the institution menu and `WPCPM_UI::surface()` read inside the frame is `institution`; the Sponsor guide still names `sponsor` and the Administrator guide `administrator`; `audience_of()` of an update institutions read is `institution` for a member, `institution` for an Administrator with `?wpcpm_institution_view=$rec`, `administrator` for an Administrator with no school when the Administrator Dashboard lists it, `sponsor` unchanged for a sponsors' update; `render()` for `institution` draws the institution menu and its list's links keep `$rec`.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the new suite, `bin/test-administrator-guide.php`, `bin/test-administrator-updates.php`, `bin/test-updates.php`, `bin/test-handbook.php`, the battery (`suites=206`).
- [ ] **Step 5: Commit:** `Institution guide and Program updates: drawn in the Institution Dashboard's frame for its readers` (paths: the two classes, the new suite).

### Task 12: The theme gives the page to the frame (theme repository)

**Repository:** `~/GitHub/wpcredits-theme`, branch `feature/institution-dashboard-frame` from `main` (`46f8376`, 1.32.27).

**Files:**
- Modify: `functions.php:77` (`add_theme_support( 'wpcpm-dashboard-frame', array( ... 'institution', 'institution-guide', 'institution-updates' ) )`); `inc/dashboard.php` `wpcredits_is_frame_page()` (`:195-200`: `|| ( wpcredits_frame_names( 'institution' ) && wpcredits_is_institution_page() )`); `templates/page-institution-dashboard.html` (the bare canvas: the sponsor's template with `wpc-main wpc-main--app wpc-main--institution`, the post content only, no `header` part, no post title).
- Create: `templates/page-institution-guide.html` and `templates/single-institution-update.html`, copies of `page-sponsor-guide.html` and `single-sponsor-update.html` with `sponsor` read as `institution` in their class names only.
- Test: `bin/test-guide-frame.php` (the theme's; new checks), the theme's other suites.

- [ ] **Step 1: Write the failing checks** in `bin/test-guide-frame.php`: the support list holds `institution`, `institution-guide` and `institution-updates`; `wpcredits_is_frame_page()` is true on the institution page while the plugin's class exists and the name is supported, false without the name; `wpcredits_guide_frame_template()` for a guide whose `frame_for()` answers `institution-guide` puts `page-institution-guide.php` first; `wpcredits_update_frame_template()` for audience `institution` puts `single-institution-update.php` first; the institution template holds `wpc-main--app wpc-main--institution`, no `wporg-header` other than the sponsor template's own and no `post-title`; the body class `wpc-institution-page` still comes from `inc/template-tags.php` (unchanged).
- [ ] **Step 2: Run** `php bin/test-guide-frame.php`. Expected: fail.
- [ ] **Step 3: Implement.** No CSS changes in this task.
- [ ] **Step 4: Run** every theme suite (`for t in bin/test-*.php; do php "$t" || echo "FAIL $t"; done` from the theme root) and `php bin/check-selectors.php` with the plugin at Task 11's commit beside it (expected: today's report, no new line).
- [ ] **Step 5: Commit** in the theme repository: `Institution Dashboard, its guide and its updates on the frame's bare canvas` (paths: the five files, the suite).

### Task 12b: The rig's Stage A scenes (rig files only)

**Files:** Modify `bin/ui/scenes.mjs` (section B, `:142-146`, grows), `bin/ui/seed.mjs` (nothing new expected; the seed already makes Example School of Design settled with two students and a report draft, and Example University with a copy in review); `bin/js/ui-rig.js` (checks).

**Interfaces:**
- Produces, in `scenes.mjs`: `export const INSTITUTION_WIDTHS = [390, 601, 700, 716, 768, 782, 783, 880, 900, 927, 928, 1000, 1015, 1024, 1110, 1200, 1280];` and `export const INSTITUTION_VIEWS = ['overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events', 'administrators'];`; for each reader (`representative` {DESIGN_LOGIN}, `representative-agreement-in-review` {UNIVERSITY_LOGIN}, `tutor` {DESIGN_TUTOR_LOGIN}, `admin-viewing-school` with `wpcpm_institution_view={DESIGN_RECORD}`, `admin-no-school`), one scene per view the reader has, `all-open`, at `INSTITUTION_WIDTHS`; the old addresses (`?wpcpm_institution_student=`, `?wpcpm_report=2026-H1`, `#wpcpm-import`) as scenes that must land on their view; the guide (`/institution-guide/`) and an update in this frame for a representative and an Administrator. `seed.mjs` gains a tutor login token `{DESIGN_TUTOR_LOGIN}` (the seed adds one invented tutor at Example School of Design through the Add a tutor form, `tutor.example@example.org`).
- [ ] **Step 1:** The checks in `bin/js/ui-rig.js`: every new scene's file name unique at each width; every reader's scenes hold the views of design 3's table for that reader and no other (a tutor has no `enroll` or `events` scene; a locked one only `overview` and `agreement`); the widths are the seventeen.
- [ ] **Step 2: Run** `php bin/test-ui-rig.php`. Expected: fail.
- [ ] **Step 3: Implement.** Today's four institution scenes keep their names (the comparison needs them).
- [ ] **Step 4: Run** `php bin/test-ui-rig.php` and the battery.
- [ ] **Step 5: Commit:** `UI rig: the Institution Dashboard's views for every reader at seventeen widths, the old addresses, its guide and an update` (paths: the three files).

### Gate A (the controller's; not an implementer task)

The brief is in "The gate briefs" below. Gate A passes when the suites prove the routing and every way back, and the pictures show every view in the frame with the old cards in it, every other surface `same`. Stage B does not start before it passes.

---

## Stage B: every card on the parts, then the switch on

Every task of Stage B: the parts' branch is taken only when `WPCPM_UI::on()` and is tested with `WPCPM_UI::set_live( array( 'administrator', 'institution' ) )` and `enter( 'institution' )`; the old branch is tested with `set_live( null )` and must print what it printed at `c0dfdf0a` (md5 digest). A renderer shared with a surface that stays off is also tested under that surface's name (`enter( 'report-cards' )`, `enter( 'sponsor' )`, `enter( 'wpcpm-institutions/accounts' )`, `enter( 'wpcpm-institutions/reports' )`) with `institution` live: old markup, byte for byte. Each task registers its drawn markup with the layout harness, with the long fixture, and reports `0 faults` at the eight widths before it reports.

### Task 13: The file box

**Files:**
- Modify: `includes/class-wpcpm-ui.php` (new `file()` after `choices()` at `:413`); `assets/css/parts.css` (the file box); `assets/js/parts.js` (the file job).
- Create: `bin/test-parts-file.php`, `bin/js/parts-file.js`.

**Interfaces:**
- Produces: `WPCPM_UI::file( array $args ): string`. Args: `id`, `name`, `label`, `accept` (as today's control's), `need` (`required`, `optional`, ''), `hint`, `problem`, `attrs`. Markup: `<div class="wpcpm-ui-field wpcpm-ui-file"><label class="wpcpm-ui-field__label" for="{id}">{label}{need}</label><input class="wpcpm-ui-file__input" type="file" id="{id}" name="{name}" accept="..."{ required}{ aria-describedby}><span class="wpcpm-ui-file__row"><button type="button" class="wpcpm-btn wpcpm-btn--secondary wpcpm-ui-file__choose" hidden aria-controls="{id}">Choose a file</button><span class="wpcpm-ui-file__name" aria-live="polite" hidden>No file chosen</span></span>{hint}{problem}</div>`. Without the script the browser's own control shows and the button and name stay hidden; with it the input is visually hidden (never `display: none`, so its label, `required` and the upload work as today), the button opens it and the name follows the choice (parts design 2.4, 3.2 job 3). "Choose a file" and "No file chosen" are new strings.
- [ ] **Step 1: Write the failing checks:** the markup above for a required box with a hint; `required` on the input only; the label's `for` the input; with no script nothing a person needs is `hidden` (the input is not); the harness: after load the button and the name are shown, the input carries the visually hidden class, pressing the button calls the input's `click()`, a change sets the name to the file's name; with no file the name reads "No file chosen"; a mutation proof (without the change listener, the name scenario fails). In the CSS checks of this suite: the 12px gap, the name muted 14px, the hidden input clipped and not `display: none`.
- [ ] **Step 2: Run** `php bin/test-parts-file.php`. Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** it, every `bin/test-ui*.php` and `bin/test-parts-js.php` (untouched), the battery (`suites=207`).
- [ ] **Step 5: Commit:** `Parts: the file box, Choose a file and the name beside it, the browser's control kept in the form` (paths: the three modified, the two new).

### Task 14: Viewing as institution, the compact control

**Files:**
- Modify: `includes/class-wpcpm-dashboards.php` (`render_switcher()` `:302-375`: a parts' branch; `render_combo()` `:390` read); `assets/js/switcher.js` (an opt-in: a form carrying `data-wpcpm-pick-submits` submits when a name is picked); `includes/modules/class-wpcpm-institutions-dashboard.php` (the switcher printed at the end of the head's first row instead of above the head, when on).
- Create: `bin/test-institution-switcher.php`, `bin/js/switcher-pick.js`.

**Interfaces:**
- Produces, `render_switcher()` with `WPCPM_UI::on()` and `WPCPM_UI::surface() === 'institution'` only: `<form class="wpcpm-ui-viewing" method="get" data-wpcpm-pick-submits>{keep and page_id hidden fields as today}<label class="wpcpm-ui-viewing__label" id="{id}-label" for="{id}">Viewing as institution</label>{render_combo(), 320px, 40px, describedby "{id}-note"}<span class="screen-reader-text" id="{id}-note">Only Administrators see this control.</span><noscript>... </noscript>` with today's Show as `WPCPM_UI::button( kind secondary )` printed inside a wrapper the script hides (`data-wpcpm-noscript`), so without script the sorted select and Show work as today. The label muted 14px beside its box (the parts' one exception, parts design 2.4); under 800px of main area it takes its own line under the H1 at full width. Every other caller and every other surface: today's markup byte for byte (ruling 2).
- Produces, `switcher.js`: picking a name in a form marked `data-wpcpm-pick-submits` sets the select and calls `form.requestSubmit()` (or `submit()` where `requestSubmit` is missing); Enter in the field still never submits a form without the mark ("Nothing here submits" stays true for them; the file's comment says the one exception).
- In the dashboard: the head's first row holds the page name and, at its end, the switcher (Overview and every view, and the choice state's main area as its one field).
- [ ] **Step 1: Write the failing checks:** on the Institution Dashboard with the surface on: the form carries `data-wpcpm-pick-submits`, the label is "Viewing as institution", the hint for screen readers is "Only Administrators see this control." tied by `aria-describedby`, Show is a secondary button inside the no-script wrapper, the `keep` fields as before; with `enter( 'sponsor' )` and `institution` live the Sponsor's switcher prints today's markup (digest); with `enter( 'report-cards' )` the same; the harness: picking a name in a marked form submits it once with the select's value; in an unmarked form picking does not submit and Enter does not submit; `bin/test-dashboard-switcher.php` passes untouched.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the new suite, `bin/test-dashboard-switcher.php`, `bin/test-sponsors-dashboard.php`, the layout harness (the head with the control, the 40-character school name, at the eight widths), the battery (`suites=208`).
- [ ] **Step 5: Commit:** `Institution Dashboard: Viewing as institution as one small control in the head, the school's page loading on a choice` (paths: the three modified, the two new).

### Task 15: The Students view: filters, cohort summary, groups, students

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-roster-view.php`: `render_filters()` `:374-486`, `render_export()` `:515-543`, `render_strip()` `:558-631`, `render_group()` `:678-717`, `render_table()` `:766-782`, `render_student()` `:796` onward and `cells()` `:973`, `render_unlinked()` `:1351`, each with a parts' branch.
- Test: `bin/test-institution-roster-view.php`, `bin/test-institutions-dashboard.php`.

**Interfaces:**
- Consumes: `WPCPM_UI::field()` (select, search), `button()`, `fold_head()`, `inner_open()`, `inner_close()`, `table_open( keyvalue )`, `cell()`, `tag()`, `breakable()`, `whole()`, `list_open()`, `row()`, `list_close()`, `heading()`, `intro()`.
- Produces, with `on()`:
  - **Filter bar** (design 6.1): the same GET form, the same names (`wpcpm_cohort`, `wpcpm_roster_status`, `wpcpm_roster_search`, the Administrator's `wpcpm_institution_view`, `page_id` without pretty addresses) and now a hidden `wpcpm_view=students`; one row of fields: Cohort (`field( select )`, id `wpcpm-roster-cohort`), Group (select, id `wpcpm-roster-status`), Search (`field( search )`, 320px, id `wpcpm-roster-search`, today's `search_hint()` as its hint, no placeholder); then Show (secondary), Clear the filters (quiet link, while a filter is set), Download this cohort (CSV) (quiet link, the export's own fence). Under 800px of main area the fields stack at full width.
  - **Cohort summary:** `<section class="wpcpm-roster__summary">` with `heading( <cohort label>, 'h3' )` and today's two sentences as `intro()`s; no colored edge.
  - **The four groups** (ruling 1, P7): `<section class="wpcpm-ui-fold-card wpcpm-roster__group wpcpm-roster__group--{key}" id="wpcpm-roster-{key}"><details class="wpcpm-ui-fold"{ open by today's rule}><summary class="wpcpm-ui-fold__head">{fold_head( size card, title label, phrase _n( '%s student', '%s students', $count ) )}</summary><div class="wpcpm-ui-fold__body">`, the group's sentence the first line, "No students in this group." or "No students in this group match the filters." as the empty line, then the students. The first group's section also carries the anchor `wpcpm-roster` on the roster's wrapping section (the `VIEW_CARDS` key `roster`).
  - **Each student:** an inner fold (`inner_open( title <name as a link to the card when the student has an account, through breakable()>, second <the dates whole and the mentor>, media <the 44px photo or initials> )`), the program's status as `tag()` in the head (tones as today's badge: green for on the program, neutral otherwise), the body a key and value table of the permitted columns (Program, Dates, Days left, Mentor, WordPress.org, Team, Website, Field of study, Tutor, Hours): WordPress.org and Website token cells; an empty value "Not recorded" with the `empty` kind; dates through `whole()`. On a phone the parts' two-column head, the chevron pinned top right. Open when the group holds one student, as today.
  - **Enrollment not recorded yet:** a card fold like a group, phrase in words, today's sentence, a list of `row()`s (the name, a status tag; for an Administrator the address as a fact).
- [ ] **Step 1: Write the failing checks** (surface on, the suite's 13-student group and the 40-character names):
  - the filter form's fields and hidden inputs (names and values) are today's plus `wpcpm_view=students`; the three fields are `wpcpm-ui-field`s with labels above; Show is secondary; Clear only with a filter; the export link only when `ACT_EXPORT` is allowed.
  - **ruling 1:** with 3 current, 2 waiting, 1 finished, 0 not started, the `open` attributes are `current` open, `waiting` open, `finished` closed, `not_started` closed; with 13 current, `current` is closed; phrases "3 students", "2 students", "1 student", "0 students".
  - each student's inner fold, its tag and its table; a name with a 40-character word comes through `breakable()`; "Not recorded" carries `wpcpm-ui-cell--empty`; an Administrator's search fields as today (`search_fields()` unchanged).
  - **a tutor's narrower rights:** the columns a tutor's decision scopes are the same set as today's (`WPCPM_Institution_Policy::scope()` called the same way).
  - the old branch: today's markup (digest) for the roster as the Institution Dashboard drew it at `c0dfdf0a`.
  - Register with the layout harness: the whole Students view with 13 students, every fold open, an Administrator's filter bar, and the enrollment-not-recorded card; measure each key and value table's least width and write it in the commit message.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** both suites, the layout harness (`0 faults` at the eight widths), the battery, the standards and the static analysis.
- [ ] **Step 5: Commit:** `Institution Dashboard: the Students view on the parts, its filters one row of fields, its groups and students as folds, behind the switch` (paths: the class, the two suites).

### Task 16: A student's card

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-student-view.php` (`render()` `:125`, `render_identity()` `:318`, `render_invite()` `:384`, `render_row()` `:569`, `render_report()` `:659`, `render_correction()` `:737`, `render_export()` `:796`, `render_status()` `:842`, `render_footer()` `:867`); `includes/modules/class-wpcpm-institution-resend.php` (`render()` `:140`, `render_line()` `:191`); `includes/modules/class-wpcpm-institution-student-form.php` (`render_form()` `:283`, `render_field()` `:436`); `includes/modules/class-wpcpm-institution-students.php` (`render_form()` `:444`, `render_button()` `:549`, `render_note()` `:579`).
- Test: `bin/test-institution-student-view.php`, `bin/test-institution-resend.php`, `bin/test-institution-student-form.php`, `bin/test-institution-graduate.php` (it covers `WPCPM_Institution_Students::render_form()`; there is no `bin/test-institution-students.php`).

**Interfaces:**
- Consumes: `item_header()`, `table_open( keyvalue )`, `cell()`, `inner_open()`, `inner_close()`, `field()`, `actions()`, `button()`, `decide()`, `tag()`, `breakable()`, `whole()`, `WPCPM_Dashboard_Card::open()` (key `student-status`, prefix `wpcpm-`, `id_on` section, title "Finishing this placement").
- Produces, with `on()` (design 6.1, the student's card): the item header (the photo, the name through `breakable()`, the program's status tag, the facts); the sign-in line with Send invitation or Resend invitation (secondary, the same form); the key and value table (Program, Internship duration, Cohort, Field of study, Tutor, WordPress.org, Contribution teams, Personal website, Mentor, Mentor email; "Not set" and "No mentor yet" with the `empty` kind; Mentor email, WordPress.org and Personal website token cells); inner folds "Student Report Card" (fetched on open, the same script hook) and "Correct this student's details" (open when its save left a sentence): five fields, label above, dates 200px, Field of study a select, Save changes primary, the same names and ids (`wpcpm-student-form` anchor kept); "Download this student's details and course grades as a CSV file" a quiet link; the read line; then its own card "Finishing this placement" (anchor `wpcpm-student-status`) with a Decide row: Mark as graduated (primary, only while switched on) and Mark as dropped out (danger), each a form that posts at once with today's browser question (`confirm`), today's sentence when it offers nothing. No "Back to the roster" (Task 9).
- [ ] **Step 1: Write the failing checks:** the posted fields of each of the four forms (resend, correct, graduated, dropped out) unchanged, form by form; the refusal of a correction tied to its box and the fold open; kept typing kept; the questions unchanged; Mark as graduated absent while switched off with today's sentence in its place; the anchors `wpcpm-student-form`, `wpcpm-student-status`, `wpcpm-student-invite` on the page; a 40-character student name and a 48-character mentor email do not widen the card (layout harness); the old branches: digests.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the four suites, the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: a student's card on the parts, Finishing this placement its own card with one Decide row, behind the switch` (paths: the four classes, the four suites).

### Task 17: Enroll students

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-import-form.php`: `render()` `:107`, `render_form()` `:229` (the fields at `:259-410`), `render_preview()` `:532`, `render_confirm()` `:612`, `render_progress()` `:656`, `render_row()` `:703`, `render_outcome()` `:728`, `render_exceptions()` `:762`, `render_message()` `:1599`.
- Test: `bin/test-institution-import-form.php`, `bin/test-institution-import.php`.

**Interfaces:**
- Consumes: `WPCPM_Dashboard_Card::open()` (key `import`, prefix `wpcpm-`, `id_on` section, title "Enroll students"; the view's card prints its head, M22 does not apply: the view's title is the same words, so it prints no head and its body is the view's content), `field()`, `choices()` (one checkbox as its own question), `file()` (Task 13), `table_open()`, `cell()`, `button()`, `actions()`, `waiting_sentence()` (Task 4).
- Produces, with `on()` (design 6.2): Program (select, at most 320px and never past the card), Start date and End date (200px; "End date" Optional, hint "If you know it." new), the notified checkbox its own question (Required), One student (Full name, Email address, Field of study, Tutor) as a field set, Or a list: the file box with "Download a sample list" beside it, and the paste box (a text area at the card's width); Check the list (primary). The check: the count sentence, the ignored columns line, the check table: columns Line (`num`), Name (`text`, the name through `breakable()`), Email address (token), What happens (`text`), widths decided by measure and written in the commit message; then Create these student records (primary, today's question) and Throw this list away (danger, today's question); progress and outcome as today's sentences, Continue (secondary). The ids `wpcpm-import`, `wpcpm-import-program`, `wpcpm-import-start`, `wpcpm-import-end`, `wpcpm-import-notified`, `wpcpm-import-file`, `wpcpm-import-paste` kept; the two selects that ran past their card at 390 (340 and 342px against 329) must measure inside it.
- [ ] **Step 1: Write the failing checks:** every form's posted fields unchanged (check, confirm, cancel, continue); a refused check keeps the typing and ties the sentence to its box; the check table's headers, `data-label`s and kinds; a 13-row list with a 40-character name and a 48-character address; the old branch digests; the layout harness: the form, the check with 13 rows, progress and outcome, `0 faults`; the check table stacks under 800px of main area and its least width as a table is in the commit message.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** both suites, the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: Enroll students on the parts, the check as a table that stacks, behind the switch` (paths: the class, the two suites).

### Task 18: Semester reports

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php`: `render()` `:211`, `render_index()` `:341`, `render_generate_form()` `:477`, `render_member_index()` `:511`, `render_reading()` `:597`, `render_draft_form()` `:634` (its parts' branch `render_draft_form_parts()` `:674` already exists for the Administrator Dashboard), `render_editor()` `:827`, `render_header()` `:886`, `render_form()` `:972`, `render_section_field()` `:1021`, `render_quote_picker()` `:1088`, `render_actions()` `:1191`, `render_button_form()` `:1259`, `render_revisions()` `:1311`; `assets/css/report-print.css` (the on-screen monospace rule of the text boxes, `:151-156`, scoped so the print rules stay).
- Test: `bin/test-semester-report.php`, `bin/test-institutions-screen.php` (the wp-admin tab).

**Interfaces:**
- Produces, with `on()` and the surface `institution` (design 6.3): the view's card prints no head (M22); the index a `list_open()` of `row()`s: the semester's name (whole), its state as a tag (Approved green; Draft and the others neutral), its facts ("Approved on" and the day, whole), its quiet actions: View and Download PDF for a school; for an Administrator Open, or the Draft now row through `render_draft_form()` (which already takes its parts' branch when on); a semester being prepared a row with today's sentence and no action. Reading: the item header (title, tag, facts), Download PDF (secondary), the document as it prints (`render_document()` untouched). The editor (Administrators): the item header, the section headings as in-card headings, the text boxes as text areas in the page's face, the quote picker a choice group, Save the report (primary of its form), then one row of Print this report and Check the students' answers again (secondary), then a Decide row: Approve this report (primary) or Reopen for editing (secondary), at once with today's questions; Revisions an inner fold. The wp-admin Semester reports tab (`wpcpm-institutions/reports`) stays off: today's markup, its back link included.
- [ ] **Step 1: Write the failing checks:** every form's posted fields unchanged (generate, draft, save, refresh consent, approve, reopen, restore, ask); a refused save keeps the stash sentence; the states' tags and tones; the semester's name and day whole; with `enter( 'wpcpm-institutions/reports' )` and `institution` live, today's markup (digest); the print document's markup unchanged (digest of `render_document( $post, true )`); the layout harness: the index with six semesters for a school and for an Administrator, a report read, the editor with every fold open, `0 faults`.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** both suites, the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: Semester reports on the parts, the editor's Decide row, the wp-admin tab unchanged, behind the switch` (paths: the class, the sheet, the two suites).

### Task 19: Your institution: the details card

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-details-card.php`: `render()` `:142`, `render_rows()` `:243`, `render_waiting()` `:302`, `render_decide()` `:341`, `render_outcome()` `:381` (through `outcome_sentence()`), `render_form()` `:449`, `render_field()` `:519`; `includes/modules/class-wpcpm-institution-tracks.php` (Tracks offered, Change the tracks); `includes/modules/class-wpcpm-institution-assignment.php` (Change who follows it up).
- Test: `bin/test-institution-details-card.php`, `bin/test-institution-details.php`, `bin/test-institution-tracks.php`, `bin/test-institution-assignment.php`, `bin/test-institution-request.php`.

**Interfaces:**
- Produces, with `on()` (design 6.4): the card (key `details`, anchor `wpcpm-details` kept), phrase "What the program has on record."; two in-card headings, Contact and agreement and Name and address, each its key and value table (6 rows and 5 rows; Students study in a list of languages, the page address and the contact's email token cells; a 40-character school name breaks; "Not given yet" with the `empty` kind; the waiting line under its row) and then its inner fold, "Correct these" with "(saved at once)" as its second piece, "Ask to correct these" with "(an Administrator checks first)"; the forms: the application's own questions as fields, the languages a choice group, Country the one combobox; Save the details (primary) and Send to an Administrator (primary), one per form; while a request waits, "Waiting for an Administrator:" and its facts under each row it changes, with Withdraw it (quiet red, today's question); the outcome line under the second table; Tracks offered a heading and a list; for an Administrator the inner folds Change the tracks (a choice group, one primary) and Change who follows it up (the combobox, one primary). The ids `wpcpm-details-tracks`, `wpcpm-details-assignment` kept.
- [ ] **Step 1: Write the failing checks:** each of the five forms' posted fields unchanged (save, ask, withdraw, tracks, assignment); a refused field keeps its typing, its sentence tied to its box and its fold open; the waiting line under the row it changes; a 40-character name, six languages, four tracks; the old branch digests; the layout harness: both tables, both folds open, a waiting request, `0 faults`; each table's least width in the commit message.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the five suites, the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: Your institution's details on the parts, behind the switch` (paths: the three classes, the five suites).

### Task 20: Your institution: representatives and tutors

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render()` `:102-240`, `render_member()` `:245` (shared with Manage members `:575`, `:677`: old branch kept), `render_contact_row()` `:302`, `render_remove_form()` `:345`, `render_read_line()` `:1240`, `render_message()` `:1289`); `includes/modules/class-wpcpm-institution-invite.php` (`render_message()`, `render_pending()`, `render_form()`, called at `class-wpcpm-institution-people.php:202-204`); `includes/modules/class-wpcpm-institution-tutors.php` (`render()` `:386`, `render_remove_form()` `:444`, `render_add_form()` `:469`, `render_message()` `:495`).
- Test: `bin/test-institution-people.php`, `bin/test-institution-invite.php`, `bin/test-institution-tutors.php`, `bin/test-institution-members.php`, `bin/test-institutions-accounts.php`.

**Interfaces:**
- Produces, with `on()` (design 6.4): the card (key `people`, anchor `wpcpm-people` kept), phrase `_n( '%s person', '%s people', $n )`; today's intro sentence; the people rows (parts design 2.8): the name through `breakable()`, "the institution's contact" as a neutral tag on that row, the facts under (address as a token, Representative or Tutor, how and since when), Remove or Leave (quiet red, today's questions); the named contact with no account a row with today's sentence as its note; Invitations waiting: rows with who sent it, when and the day it stops working, Resend (quiet) and Cancel invitation (quiet red, new words), 16px apart; Invite a colleague: the paragraph under the button becomes the form's intro, Email address (Required, no placeholder), Level a choice group with each option's hint, Send invitation (primary); Tutors (anchor `wpcpm-tutors` kept): rows (name, address), Remove (quiet red); Add a tutor: Name and Email (Required), Add (primary); for an Administrator, "Add or re-add an account on the Institutions screen" as a quiet link; the read line. Manage members on the wp-admin Institutions screen (`enter( 'wpcpm-institutions/accounts' )`) stays off: today's markup.
- [ ] **Step 1: Write the failing checks:** each form's posted fields unchanged (remove, leave, resend, cancel, invite, tutor remove, tutor add), with the `wpcpm_from` field as today; the questions unchanged; a refused invite keeps its typing; "Cancel invitation" is the button's words; Manage members' digest with `institution` live; five people, three invitations, a 40-character name and address; the layout harness `0 faults`.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the five suites, the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: representatives, invitations and tutors on the parts, Manage members unchanged, behind the switch` (paths: the three classes, the five suites).

### Task 21: Agreement

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-panel.php`: `render()` `:155`, `render_flash()` `:225`, `render_state()` `:985` (keeps `state_sentence()`), `render_steps()` `:1089`, `render_generate_form()` `:1132`, the upload form (`render_upload_form()`, shared with the manager's upload in wp-admin: old branch kept), and `WPCPM_Institution_Agreement_Card` (from `:1784`: `render()`, `render_download()`, `render_replace()`, `render_route()`, `render_drive_link()`); `includes/modules/class-wpcpm-institutions-dashboard.php` (`render_banner()` as the view's first line on the parts).
- Test: `bin/test-institution-panel.php`, `bin/test-institution-agreement.php`, `bin/test-agreement-generate.php`.

**Interfaces:**
- Consumes: `tag()`, `heading()`, `intro()`, `field()`, `choices()`, `file()`, `button()`, `actions()`, `WPCPM_UI::note()`.
- Produces, with `on()` (design 6.5): the view's card prints no head; a status tag leads: Accepted (green), Waiting for review (amber), Returned and Revoked (red), none for `none` and `generated` (the sentence says it). Settled: "Accepted on" and the day (whole), how it was signed or recorded, Download the accepted agreement (secondary); "Replace it ..." as the upload form's intro; while a newer copy waits, its sentence, Download the copy waiting for review (secondary) and Withdraw it (danger, today's question); for an Administrator, the Drive link (quiet). Not settled: `state_sentence()`, the three steps as an ordered list of sentences, the generate form ("The institution's name" its label, "Exactly as it should print on the agreement." its hint, both new; Generate the agreement, primary), Open that document again (secondary) once generated, the already-signed note, Download the copy we hold (secondary) and Withdraw it (danger) while it waits, the note an Administrator left (`WPCPM_UI::note()`) when returned or revoked; for an Administrator, the banner sentences first and recording the copy the program holds. The upload form: "The program's template, signed" and "An agreement of our own, signed" a choice group, the signer's checkbox its own question (Required), the file box (PDF), Upload the signed agreement (primary). The anchor `wpcpm-agreement` kept.
- [ ] **Step 1: Write the failing checks:** every form's posted fields unchanged (generate, upload, withdraw, record on file); each state's tag and tone and its sentence equal to `state_sentence()`; the manager's upload in wp-admin prints today's markup (digest with `institution` live); a refused upload keeps the choice and ties its sentence to the box; the layout harness: each of the seven states, for a member and an Administrator, `0 faults`.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the suites, the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: the Collaboration Agreement on the parts, its state as a tag, the manager's upload unchanged, behind the switch` (paths: the two classes, the suites).

### Task 22: Events and For Administrators

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-event-list.php` (`render()` `:142`, `render_actions()` `:253`: parts' branches; the Report Cards' calendars keep the old); `includes/modules/class-wpcpm-institution-history.php` (`render()` `:83`, `render_answers()` `:221`, `render_history()` `:259`, `card()` `:206`).
- Test: `bin/test-sponsor-event-list.php`, `bin/test-institution-history.php`.

**Interfaces:**
- Produces, with `on()` and the surface `institution`: Events (design 6.6): list rows: the event's name, the host and its day and time in the reader's zone, how long and the places left as facts; "You are on it." its note; Join (quiet), Leave (quiet red), Join the call (quiet link); heading words as today. For Administrators (design 6.7): lede "Only Administrators see this section." (today's); two cards, open, keys `admin-answers` and `admin-history` (anchors kept), From the application (the key and value table, questions as labels wrapping in the 320px label column) and History (a list, facts under: each sentence, then the date and time whole and who, so a long name breaks and the meta line never runs past the card).
- [ ] **Step 1: Write the failing checks:** Join and Leave post what they post; with `enter( 'report-cards' )` and `institution` live, `render()` and `render_actions()` print today's markup (digest); the History meta line with a 40-character name; the layout harness: three events, the history with twelve entries, `0 faults` (today's History meta line ran 40px past at 390).
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** both suites, `bin/test-student-modules.php` (untouched), the layout harness, the battery.
- [ ] **Step 5: Commit:** `Institution Dashboard: Events and For Administrators on the parts, the Report Cards' calendars unchanged, behind the switch` (paths: the two classes, the two suites).

### Task 23: Turn the Institution Dashboard on, and delete what only it used

**Files:**
- Modify: `includes/class-wpcpm-ui.php:23` (`const LIVE = array( 'administrator', 'institution' );`); the old branches only this page reached (each named after its search): the roster view's old filter bar, strip, groups and student cards; the student view's old markup; the import form's; the details card's; the people card's own (not `render_member()`'s); the invite's and tutors'; the panel's (not `render_upload_form()`'s); the agreement card's; the history's; `WPCPM_Institutions_Dashboard::render_module()`, `render_module_body()`, `render_identity()`, `contact_block()`, `render_help()` if no caller is left; `assets/css/institution.css` (the ranges of parts design 6.1's release 4 row, `:322`, `:408`, `:515`, `:544`, `:577-590`, `:619-622`, `:914`, `:971-1000`, `:1345`, and every other rule whose classes the search finds nowhere).
- Kept, named in the commit: `WPCPM_Dashboard_Card`'s old branch (the Sponsor Dashboard); `render_member()`'s and `render_upload_form()`'s old branches (wp-admin); `WPCPM_Dashboards::render_switcher()`'s old markup; `WPCPM_Sponsor_Event_List`'s old branches; `WPCPM_Semester_Report_Screen`'s old branches (the wp-admin tab); `WPCPM_Module_Order` (the Student Report Card); the strings P8 keeps.
- Test: `bin/test-ui.php` (expects the new `LIVE`), every suite of Tasks 15 to 22 (the off checks of a deleted branch removed, the others kept).

- [ ] **Step 1: Write the failing check:** `bin/test-ui.php`'s `LIVE` check expects `array( 'administrator', 'institution' )`. (This is the one edit to a `bin/test-ui*.php` suite this plan makes; it is the switch's own pin.)
- [ ] **Step 2: Run.** Expected: `bin/test-ui.php` fails.
- [ ] **Step 3: Turn it on.** Set `LIVE`. Delete the listed old branches and the `WPCPM_UI::on()` test around each.
- [ ] **Step 4: The searches.** For each `institution.css` range, list its classes and run `grep -rn "<class>" includes/ assets/js/ blocks/` for each; record the class, the command and what it found in the commit message body. Delete a rule only when the only hits are in branches just deleted or in the parts' own markup.
- [ ] **Step 5: Run** every suite, `php bin/test-guide-labels.php` (P8), the layout harness over every case the Stage B tasks registered (`0 faults`), the standards, the static analysis, `php bin/check-references.php`, `php bin/test-translators-pot.php` after regenerating the `.pot`.
- [ ] **Step 6: Commit:** `Institution Dashboard: on the parts; what only it used is deleted` (body: the searches; paths: every file changed).

### Task 24: The theme's dead rules (theme repository)

**Repository:** the theme branch of Task 12.

**Files:**
- Modify: `assets/css/dashboard.css`: every selector under `.wpc-institution-page` that matches a class the plugin at Task 23 no longer prints on the page, each after a search of the plugin (design 10): the movers and modules (about `730-880`), the page's opening shape and switcher (`514`, `915-1009`), the group and roster rules (`1592-1609`, `1990`), the student rows and people forms (`3227-3323`), the phone rules (`4503-4654`); a list that names the Report Cards' pages too loses only its institution selector. If release 1's theme Task 20 has not shipped: add `:not( .wpcpm-ui-choice )` to the label rule and `:not( .wpcpm-ui-choice input )` to the box rule at about `5023-5037` (parts design 6.2; this page has four choice groups). `bin/check-selectors.php`: its institution pins go with their rules.
- Test: `php bin/check-selectors.php` against the plugin at Task 23's commit; the theme's suites.

- [ ] **Step 1:** Run `php bin/check-selectors.php` against the plugin at Task 23. Expected: it reports the rules above as styling classes no longer printed (the failing check).
- [ ] **Step 2:** For each range, record the search (`grep -rn "<class>" <plugin>/includes <plugin>/assets/js`) and delete only what no surface prints.
- [ ] **Step 3:** Run `php bin/check-selectors.php` and every theme suite. Expected: all pass.
- [ ] **Step 4: Commit** in the theme repository: `The Institution Dashboard's old card, roster and form rules go, now that the plugin draws the page with its parts` (body: the searches; paths: `assets/css/dashboard.css`, `bin/check-selectors.php`).

### Gate B (the controller's; not an implementer task)

The brief is below. The guide, the rig's Stage B scenes (Task 26 runs before the gate's capture) and the version follow it.

---

## After the gates: the guide, the rig, the version, the review

### Task 25: The guides say what the screens show

**Files:**
- Modify: `docs/sections/50-institution-dashboard.md` (40 lines: the menu, the views, the head, the Overview, Help, Program updates; no arrows, no Resources), `docs/sections/51-institution-students.md` (72 lines: the Students view, a student's card with no back link, Enroll students as its own view at its address, Semester reports as its own view), `docs/sections/52-institution-details.md` (55 lines: Your institution, Agreement, Cancel invitation, the file box); the Administrator guide's parts that name the Institution Dashboard: `docs/sections/33-admin-access.md` (`:51`, `:65`, `:153-168`, `:201`), `docs/sections/34-admin-operations.md` (`:160`, `:239`, `:290`, `:1009`), `docs/sections/61-admin-steps-institutions.md` (`:97`, `:117`, `:154`, `:175`, `:204`, `:264-268` Open a school's Institution Dashboard: the choice state and the compact Viewing as, `:285`, `:306`, `:329` Enroll students at its address, `:354`), `docs/sections/30-admin-wpadmin.md` (`:529`, `:544`, `:572` only where they describe the dashboard), `docs/sections/60-admin-start.md:45`; the strings P8 kept, deleted; `languages/` through the `.pot` command; `docs/build/` through `php bin/build-docs.php` twice.
- Test: the guide's four suites, `bin/test-admin-menu.php` (its institution guide checks), `bin/test-handbook.php`, `bin/test-translators-pot.php`, `bin/check-spelling.php`, `bin/check-references.php`.

- [ ] **Step 1:** Write each section's new text: bold is the exact name on screen ("Overview", "Students", "Enroll students", "Semester reports", "Your institution", "Agreement", "Events", "For Administrators", "Program updates", "Institution guide", "Viewing as institution", "Choose an institution", "Cancel invitation", "Choose a file"); say that a menu item is the way back, that a count beside an item says how much waits there, that "New" rows are news and wait for nothing, that an Administrator chooses a school first and the page does not remember it. Short sentences; hyphens only; "Administrators"; no names.
- [ ] **Step 2:** Delete the strings P8 kept, now that no covered section names them. Regenerate the `.pot`.
- [ ] **Step 3:** `php bin/build-docs.php` twice; run the guide's four suites, `bin/test-admin-menu.php`, `bin/test-handbook.php`, `bin/test-translators-pot.php`, `php bin/check-spelling.php`, `php bin/check-references.php`, the battery.
- [ ] **Step 4: Commit:** `Institution guide and Administrator guide: the Institution Dashboard's menu, views, Overview and Viewing as` (paths: each section, `docs/build/` files changed, `languages/` files, each class whose kept string was deleted).

### Task 26: The rig's Stage B scenes and seed

**Files:** Modify `bin/ui/scenes.mjs`, `bin/ui/seed.mjs`, `bin/ui/states.php` (only if a state is needed), `bin/js/ui-rig.js`.

**Interfaces:**
- Produces, for every view of `INSTITUTION_VIEWS` and every open state of Gate B's list (below): a scene, for the representative, the tutor and the Administrator viewing the school, at `INSTITUTION_WIDTHS`; the seed's 40-character words in a school's name, a student's, an address and a tutor's (invented, "Example" in each, as `GATE_7` already holds); each Decide row, inner fold and form open by `press` or `openFold` on its words; a refusal kept (the seed posts one invite with a malformed address through the form and the scene photographs the bounce).
- [ ] **Step 1:** The checks in `bin/js/ui-rig.js`: each open state of Gate B's list has a scene for each reader who can see it; file names unique; the long words are in `INVENTED`.
- [ ] **Step 2: Run** `php bin/test-ui-rig.php`. Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php bin/test-ui-rig.php` and the battery.
- [ ] **Step 5: Commit:** `UI rig: every view and open state of the Institution Dashboard on the parts, with long words` (paths: the files changed).

### Task 27: Version and changelog

**Files:** `wpcredits-program-manager.php` (`:6` header, `:22` `WPCPM_VERSION`), `readme.txt` (`:7` Stable tag, a `= 1.123.95 =` entry above `:299`), `languages/` (the `.pot`); in the theme repository `style.css:7` and `readme.txt` (Stable tag and a `= 1.32.28 =` entry).

- [ ] **Step 1:** Read the version on main and on every branch of both repositories (`git branch --format='%(refname:short)'` and `git show <branch>:wpcredits-program-manager.php | grep Version`; the theme's `style.css` likewise); take 1.123.95 and 1.32.28 if free, else the next free, and say which in the report. The `_doing_it_wrong()` versions any task of this plan wrote must equal the plugin version taken.
- [ ] **Step 2:** The plugin changelog, in plain words for Administrators and schools, four to six bullets: the Institution Dashboard in the dashboards' frame with a menu down the side and a page per item; the Overview with the school's figures, what waits and what is new; Enroll students at its own address; an Administrator chooses a school first, with Viewing as in the head; the Institution guide and Program updates in the same frame; nothing else changes on any other screen. The theme's: one bullet (the page, its guide and its updates on the frame's bare canvas; the old rules removed). No names, hyphens only.
- [ ] **Step 3:** The battery, the standards, the static analysis, `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/test-translators-pot.php`, the theme's suites and `check-selectors.php`.
- [ ] **Step 4: Commit** in the plugin: `1.123.95: the Institution Dashboard in the dashboards' frame, on the shared parts`; in the theme: `1.32.28: the Institution Dashboard on the frame's canvas`.

### Task 28: The whole-branch review (the controller dispatches it)

A reviewer who did not build any task reads the whole diff of both repositories from `c0dfdf0a` and `46f8376`, against the design, this plan and the five Review Focus lines, and runs the battery, the standards, the static analysis, the layout harness over every registered case and the theme's checks. Findings are fixed by the controller's dispatch, each with its test. The plan ends here: **ready for the owner's pictures.** No mirror, no deploy, no release before 20 November 2026 and the owner's yes.

---

## The gate briefs (for the rig operator)

Both gates run on the local Docker copy only, with `bin/ui/` (`copy.sh prepare`, `swap`, `snap.mjs`, `measure.mjs`, `compare.mjs`, `restore`), in the operator's own worktree, the controller cherry-picking its rig commits between tasks. Both builds of a comparison are photographed from one prepared session (`copy.sh swap`), on the same UTC day.

**Common to both gates:**
- **Who:** a school's representative (Example School of Design, settled), its tutor, a locked school's representative (Example University, a copy in review; and one with no agreement), an Administrator viewing Example School of Design, and an Administrator with no school chosen.
- **Widths:** the seventeen: 390, 601, 700, 716, 768, 782, 783, 880, 900, 927, 928, 1000, 1015, 1024, 1110, 1200, 1280; at 1110 and 1200 also with the menu folded.
- **The comparison rule:** every scene whose area is not `institution-dashboard`, `institution-guide` or `institution-update` compares `same` between today's build (1.123.94) and the gate's build, every one, at every width it is taken at; one `DIFFERS` stops the gate.
- **Overflow at zero:** no OVERFLOW, H-SCROLL or CELL line on any Institution Dashboard, Institution guide or institution update scene at any width (today's lines at 390, the two Enroll students selects and the History meta lines, among them, must be gone at Gate B; at Gate A only the new blocks are held to zero, the old cards' known lines are listed and carried).
- **Keyboard:** Tab through the menu and every view; Enter and Space on each fold head; the menu drawer opens and closes on a phone; focus lands on the card a link names; the focus ring on every control.
- **The presses (Gate B; Gate A for the ways back):** one press of each form on the copy as the representative and as the Administrator viewing the school, each landing on its view with its card and its message there and the school kept: Enroll students (check, create, throw away), correct a student, mark as graduated or dropped out, resend an invitation, save details, ask to correct, withdraw it, invite, cancel an invitation, remove a member, add and remove a tutor, generate, upload, withdraw an agreement, join and leave an event, save, approve and reopen a report; each mail in the mail log as before. No mail leaves the copy.

**Gate A must photograph:** every view each reader has, closed and all open, in the frame (the old cards inside); the Overview with figures, rows of both tags and the contact card; the choice state; the old addresses (`?wpcpm_institution_student=`, `?wpcpm_report=2026-H1`, `?wpcpm_batch=`, `#wpcpm-import` with no view) each landing on its view; a locked school asking for `?wpcpm_view=students`; a tutor asking for `?wpcpm_view=enroll`; the Institution guide and a program update in this frame for a representative and for an Administrator; the menu's counts. Read: the menu's items per reader against design 3's table; the counts against the waiting rows; the head's one H1.

**Gate B must photograph, in addition:** every card open and each inner fold open (each student, Correct this student's details, Student Report Card, Correct these, Ask to correct these, Change the tracks, Change who follows it up, Revisions); every form open with a refusal kept (a malformed invite address, a correction refused); each agreement state for a member (none, generated, submitted, returned, revoked, accepted, a newer copy waiting); the student's card; the enrollment check table with thirteen rows; the report index, a report read and the editor; the compact Viewing as at every width (its own line under the H1 below 800px of main area); the file box before and after a file is chosen; the long words in a school's name, a student's, an address and a tutor's. The measures: one button height, heads 56 and 44, one field height, gaps only on the rhythm, every row action border 0. No script: with JavaScript off, every form on the page and posting, the Viewing as select with its Show. Then the before and after set, for the owner.
