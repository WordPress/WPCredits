# Administrator Dashboard

**Date:** 4 September 2026
**Component:** `wpcredits-program-manager` 1.91.0, ships as 1.92.0, with `wpcredits-theme` 1.17.0
**Status:** approved design, not yet implemented
**Depends on:** the semester report approval design of the same date (its `queue()`, `due()`, `approved_since()` and `ACTION_DRAFT`). The Sponsor module design adds cards to this page in its last phase.

Program managers today work across the wp-admin Institutions screen (eleven cards), the Mentors and Students screens, the toolbar switchers and their inbox. Nothing tells them, on one page, what is waiting for them. This document gives them that page, on the front end like the three dashboards the program's other audiences have, with the frequent actions on it.

House rules that apply to every line below: comments explain why and name the bug or decision behind the rule; no em dashes; full product names; every behaviour worth trusting has an assertion in `bin/test-*.php`.

---

## 1. Settled by the product owner (4 September 2026)

1. **A front-end page with the actions on it.** Themed like the Institution Dashboard, at `/administrator-dashboard/`, showing every queue with counts and the approve, return, resolve and draft actions right there. The wp-admin screens stay for settings, syncs and the rarer work.
2. **"All current programs running" means both views:** a totals strip per program track above one row per institution with students in progress.
3. **Order:** after the semester report flow, before the Sponsor module.

---

## 2. The architecture, in four decisions

**1. It is the Administrators module's front end, built exactly like the Institution Dashboard.** `WPCPM_Administrators` (today an informational wp-admin screen) gains `WPCPM_Administrators_Dashboard`: `ensure_page()` with the adopt-by-slug rule, `gate_page()` with `metadata_exists()` (the meta defaults to `public`, which is how the Institution Dashboard first came up ungated), a block and a shortcode, `OPT_PAGE`, `TITLE_VERSION`, and one `render()` that calls cards by name through `card()` guarded by `class_exists()`. The order of the calls is the spec, and it is the whole of it.

**2. Every card reads through one static method on the class that owns the data, and writes through the handler that already exists.** No card queries posts or options itself; it asks `WPCPM_Institution_Application::queue()`, `WPCPM_Institution_Agreement::queue()`, `WPCPM_Semester_Report::queue()`, and so on. Where a method does not exist yet it is added to the owning class and pinned by that class's suite, so the page can never show a count the owner would compute differently. Decisions are posted to the existing `admin_post_` handlers with the existing nonces; this page adds no second way to approve anything.

**3. Handlers learn where to go back, from an allowlist.** Today every handler redirects to its wp-admin screen. Each gains a hidden `wpcpm_return` field read by `WPCPM_Return::url( $default )`, which accepts exactly two values, `admin` and `dashboard`, and maps them to the wp-admin screen URL and the Administrator Dashboard URL plus an anchor. Anything else is the default. A posted URL is never followed; the plugin already refuses to redirect to a place it did not choose (`WPCPM_Request::is_explicit_redirect()`), and this keeps it that way. Outcomes keep travelling by `WPCPM_Flash`, so a decision made here shows its message here.

**4. Managers only, by the `administrator` access level and the capability.** The page is gated to the `administrator` level (`WPCPM_Content_Access` already labels it "Administrators only" and requires `CAP_MANAGE`), and `render()` re-checks `current_user_can( CAP_MANAGE )` before drawing anything, because a page can be reached through a block in some other post. A viewer without the capability sees the same "nothing to show" text the other dashboards print for the wrong audience, nothing more.

---

## 3. The page

Slug `administrator-dashboard`, title **Administrator Dashboard**, block `wpcpm/administrator-dashboard`, shortcode `wpcpm_administrator_dashboard`, `OPT_PAGE = 'wpcpm_administrator_page_id'`, `OPT_TITLE_FIXED`, `TITLE_VERSION = 1`. `WPCPM_Dashboards::links()` gains the entry (id `wpcpm-administrator-dashboard`, title "Administrator Dashboard", shown to `CAP_MANAGE` holders), guarded by `class_exists()` like the institution entry. The wp-admin Overview screen links to it. No login routing: managers land in wp-admin as they do today; a `administrator_home` setting is not added until somebody asks.

`assets/js/forms.js` is enqueued from `render()` (the double-submit guard is inert without it; the Institution Dashboard shipped without it once).

### 3.1 Render order

1. `WPCPM_Two_Factor::prompt()` for the viewer.
2. Optional `<h2>` from the shortcode `title` attribute.
3. Flash notices: the page takes every channel a card on it can produce (`institution_admin`, `institution_report`, `institution_request`, the agreement and application channels) once, at the top, and draws them in one notice area.
4. **Needs attention**, a strip of counts, not collapsible.
5. **Institution applications** card.
6. **Collaboration Agreements** card.
7. **Semester reports** card.
8. **Mentor requests** card.
9. **Programs running** card.
10. **Syncs and health** card.
11. `WPCPM_Handbook_Assistant::render_resources( 'administrator' )`: the Updates column and the administrators guide. `guides()` gains an `administrator` key pointing at the administrators guide `bin/build-docs.php` already composes; `WPCPM_Updates::levels_for()` gains `administrator` mapping to the `administrator` level plus `public`.

Every card is the canonical collapsible: `<details class="wpcpm-group wpcpm-group__disclosure">` with `<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Title <span class="wpcpm-group__count">N</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>` and a `wpcpm-group__body`. A card is `open` when its count is above zero, closed when it is zero, the rule the roster's Finished group set. Every form carries `data-wpcpm-once` and `data-wpcpm-busy`.

### 3.2 Needs attention

Eight counts, each an anchor to its card: applications waiting (`new` + `held`), agreements awaiting review, agreements overdue (submitted longer than `agreement_review_days` ago), semester report drafts to review, cohorts due for drafting, mentor requests open, mentor requests overdue (`OVERDUE_DAYS`), locked accounts (roster refusal ceiling). A count of zero is drawn muted, not hidden, so the strip always has the same shape and a manager learns where to look. The Sponsor module's last phase adds its counts here.

### 3.3 Institution applications

One row per application in `new` or `held`: reference, institution name, country and the country's routed manager (from `WPCPM_Countries::contact_of()`, information only, as decision 15 says), received date, state, a *Read* disclosure with the thirteen fields as the wp-admin queue shows them, and the six decisions as six forms posting to `wpcpm_app_approve`, `wpcpm_app_info`, `wpcpm_app_reject`, `wpcpm_app_spam`, `wpcpm_app_reopen`, `wpcpm_app_purge` with their nonces keyed `ACTION . '_' . $id` and `wpcpm_return = dashboard`. The info decision keeps its textarea. Reopen and purge appear only for rows in `rejected` or `spam`, which the card lists under a second, closed disclosure. Data: `WPCPM_Institution_Application::queue( $states )`, added if the wp-admin queue does not already read through one.

### 3.4 Collaboration Agreements

Three lists from `WPCPM_Institution_Agreement::queue()`: **awaiting review** (institution, kind, submitted date, age, an overdue mark past `agreement_review_days`, *Download* link to `wpcpm_agreement_download`, *Accept* form, *Return* form with its note field), **returned, waiting for a new upload** (institution, returned date, the note), **revoked** (institution, date, *Reinstate* form). The download stays a GET with a nonce and never opens inline, exactly as on the wp-admin card.

### 3.5 Semester reports

Three lists: **to review** from `WPCPM_Semester_Report::queue()` (institution, cohort label, drafted date and origin, rows still in progress at drafting, age; a *Review* link to the Institution Dashboard editor through `WPCPM_Institution_Roster::ARG_VIEW` and `?wpcpm_report=<key>`); **due for drafting** from `due()` (institution, cohort label, window end, in-progress rows, a *Draft now* form posting `wpcpm_report_draft` with the record and cohort as hidden fields); **approved this semester** from `approved_since( start of the current half-year )` (institution, cohort, approved date, by whom, a View link). No approve button here: approval belongs in the editor, where the manager has read the document.

### 3.6 Mentor requests

From `WPCPM_Institution_Request::open()`: institution, the note, opened date, age, overdue mark, and the *Resolve* and *Decline* forms posting `wpcpm_resolve_request` with the request's nonce. A closed disclosure lists the last twenty resolved.

### 3.7 Programs running

Two parts from one calculation, `WPCPM_Administrators_Dashboard::programs()`, which walks every institution in the pipeline index with a roster and, for each roster row, reads only `status`, `start`, `end` and `mentor`:

- **Totals strip, one tile per track** (`WPCPM_Program::track()`: `150h`, `50h`, `dev`): students in progress, signed up this semester, finished this semester. "This semester" is `WPCPM_Cohort::current()`; the numbers come from `WPCPM_Cohort::participation()` per institution, summed, so the strip and the semester report count the same thing the same way.
- **One row per institution with at least one student in progress**, sorted by institution name: institution (a link to its dashboard through the switcher), students in progress with a breakdown by status label, mentors assigned (distinct mentor record IDs across the in-progress rows) and rows waiting for a mentor, earliest and latest end date among in-progress rows, agreement summary state (`WPCPM_Institution_Agreement::summary()`), latest semester report state (`draft`, `approved`, or none). Institutions with nobody in progress are counted in one closing line ("12 more institutions with no student in progress") and not listed.

The calculation reads options only, never Airtable, and prints the roster read time it found, the way the roster strip does, so a manager knows how old the numbers are.

### 3.8 Syncs and health

One row per sync (students, mentors, institutions, and sponsors once it exists): last run, next scheduled run, state, last error verbatim, a link to the wp-admin screen where it can be started. Then: locked accounts (from the roster's refusal ceiling log, with the same unlock-by-tomorrow sentence the Institutions screen prints), the private storage probe verdict and date, the mail log's last send, the invitation queue run if one is in progress. Everything here is read-only and links out; the syncs are started where their progress can be watched.

---

## 4. `WPCPM_Return`

`includes/class-wpcpm-return.php`:

- `const FIELD = 'wpcpm_return'`, `const ADMIN = 'admin'`, `const DASHBOARD = 'dashboard'`.
- `field( $where )` prints the hidden input.
- `url( $default )` reads the posted value through `WPCPM_Request::posted_key()`, returns the Administrator Dashboard URL (with `#<card>` when a `wpcpm_return_to` anchor key is posted and is one of the card ids) for `dashboard`, and `$default` for anything else, including `admin`, an empty value and an absolute URL.

Handlers that gain the field, each changing one line (its final redirect): the six application decisions, the agreement accept, return, reinstate and on-file actions, the request resolve action, `wpcpm_report_draft`. Each suite that scrapes these handlers' forms already asserts the field names; they gain the return field.

---

## 5. Theme, 1.17.0

- `templates/page-administrator-dashboard.html`: a copy of the institution template with `wpc-main--administrator`. The binding is the slug; the plugin's `SLUG` and this filename are one contract.
- `inc/template-tags.php`: `wpcredits_is_administrator_page()` (the stored page ID, the block, or the shortcode, the three ways every dashboard is reachable) and `wpcredits_is_dashboard_page()` gains it. `functions.php`: `wpc-administrator-page` in `wpcredits_body_class()`. Both edits together, or the page renders as an article with no skin, as the Institution Dashboard did for two releases.
- `assets/css/dashboard.css`: rules for the attention strip (a row of tiles that wraps, muted zero), the programs table (numeric columns right-aligned, the status breakdown as a muted second line), and the six-decision button row (which the wp-admin queue draws with core's button classes that the front end does not load). Every selector goes through `bin/check-selectors.php`.
- `readme.txt` and `style.css` bumped.

---

## 6. Settings

None new. `agreement_review_days`, `report_notify` and `OVERDUE_DAYS` are read as they are.

---

## 7. Data model additions

- Options: `wpcpm_administrator_page_id`, `wpcpm_administrator_page_title_fixed` (named like the other dashboards' options).
- No post type, no meta, no cron.
- `uninstall.php` deletes the two options; the page itself stays, as the other dashboards' pages do (uninstall never deletes content).
- `docs/sections/34-admin-operations.md` gains the page and the two options.

---

## 8. Tests

`bin/test-administrators-dashboard.php`, new, in the standard shape (WordPress stubbed, collaborators stubbed to their contracts, the real class required, a fixture, then assertions):

- A viewer without `CAP_MANAGE` gets the nothing-to-show text and no `<form`.
- With a fixture holding two applications, one agreement awaiting review (one overdue), two report drafts, one due cohort, one open request, two institutions with students in progress across two tracks: every card renders in the order of section 3.1, each count in the strip matches its card, cards with a count are `open` and the empty ones are closed.
- Every decision form carries the right action, a nonce keyed to the item's ID, and `wpcpm_return = dashboard`.
- `programs()` returns the expected per-track totals and per-institution rows from the fixture rosters; an institution with nobody in progress is not a row and is counted in the closing line; mentors assigned counts distinct IDs.
- `WPCPM_Return::url()` returns the dashboard URL for `dashboard`, the default for `admin`, for an empty value, and for `https://example.com/`, and appends an anchor only for a known card id.
- The Syncs card prints the last error verbatim and escaped.
- `bin/test-handlers.php` sees the handlers still registered; the application, agreement and request suites scrape the return field.

The theme's `bin/check-selectors.php` passes with the new rules, and `bin/test-updates.php` sees the `administrator` audience map to its level.

---

## 9. Demonstrates, on wordpresseducation.org

Signed in as a manager: the page lists the TEST institution's application (if one is held), its agreement state, the semester report draft from the previous piece with Review and the due cohort with Draft now, the open mentor request from the pilot, the programs table with the TEST institution's fixture students, and the three syncs' last runs. Approving the TEST agreement from the page lands back on the page with the flash. A TEST representative visiting the page sees the nothing-to-show text.

---

## 10. Deliberately not in scope

- Replacing the wp-admin Institutions screen; its sync panel, pipeline, provisioning, reconciliation, consent, discrepancies, template and storage cards stay where they are.
- Approving a semester report from this page.
- Per-manager assignment or filtering of the queues.
- Charts. Counts and tables only.
- Routing managers to this page at login.
