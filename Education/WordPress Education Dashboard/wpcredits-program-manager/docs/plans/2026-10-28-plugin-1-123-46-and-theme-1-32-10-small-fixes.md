# Plugin 1.123.46 and theme 1.32.10: small fixes left from 1.123.44 and 1.32.9

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Mentors, Institutions and Sponsors invitation cards stop calling an empty list "invited", and the theme's signed-in Menu panel handles a navigation that would hold only the panel-only buttons; plus the comments 1.32.9's reviews parked.

**Architecture:** Plugin: each of the three screens passes the invitation card the `none` sentence that 1.123.44 added for the Students screen (`WPCPM_Mail::render_invite_card()`'s optional `none`, and `WPCPM_Mail::everyone( $role )` to tell "nobody holds the role" from "everybody is invited"). Theme: a render-time check in the navigation filter that leaves out the signed-in reader's panel-only buttons when no other item would show.

**Tech Stack:** PHP, the plugin's `bin/test-*.php` suites, the theme's `bin/test-*.php` suites.

**Spec:** None written: these are parked findings the product owner asked to have done ("Do: 1, 2, 3, 4, 5", 28 Oct 2026). The findings: plugin `.superpowers/session-backups/2026-10-28/plugin-1-123-44-sdd/task-1-report.md` item 3 of its concerns; theme `.superpowers/session-backups/2026-10-28/theme-1-32-9-sdd/progress.md` (Minor 3, Minor 6, the "two buttons" comments, "the site's owner" in `inc/partners.php`).

## Global Constraints

- Plain hyphens only: never an em dash or an en dash, in code, comments, strings, readme or commits.
- US English. "Administrators" is the one name for the people who run the program; in comments, people are named by role ("the product owner"), never by name.
- Every plugin or theme edit bumps the version everywhere it is written, with a changelog entry and the translation template (plugin `bin/make-pot.sh`).
- New on-screen strings are translatable, with a translators comment where they carry a placeholder.
- The theme ships after the plugin; neither needs the other's new code.

## Review Focus

1. A role that holds accounts of which none was ever invited: the card offers its button, as before (the `none` sentence must only show when the role holds nobody).
2. A role whose every account was invited: the card still says "Every one of the ... has been invited." as before.
3. A signed-in reader whose navigation holds a visible item besides the panel-only buttons (the live state: Partners alone, or a saved menu): the buttons still show in the Menu panel exactly as 1.32.9 draws them.
4. A visitor (signed out): the navigation and its buttons are unchanged.
5. The Site Editor and REST reads of the navigation: never offered the buttons, as before.

---

### Task 1 (plugin): the invitation cards say when nobody holds the role

**Files:**
- Modify: `includes/modules/class-wpcpm-mentors.php` (`render_invitations()`, about line 617), `includes/modules/class-wpcpm-institutions.php` (about line 2875), `includes/modules/class-wpcpm-sponsors.php` (about line 3103).
- Test: the three screens' suites (`bin/test-mentors-screen.php`, `bin/test-institutions-screen.php`, `bin/test-sponsors-screen.php`), following how `bin/test-students-screen.php` pins the Students card's `none` sentence from 1.123.44.

**Interfaces:**
- Consumes: `WPCPM_Mail::render_invite_card( array $args )` with its optional `none` (string, the sentence printed when `pending` is empty and nothing is held), and `WPCPM_Mail::everyone( $role )` (every account holding the role, as plugin IDs). Read how `WPCPM_Students::render_invitations()` and `card_words()` use them.

- [ ] **Step 1:** Check whether each screen's `never_invited()` is narrowed by a filter on its Accounts tab. If it is, word `none` for that filter the way the Students screen does; if not, the only empty case to word is a role that holds no account.
- [ ] **Step 2: Failing tests**, one per screen: with no account of the role, the card prints the screen's `none` sentence and not "Every one of the"; with accounts all invited, it prints "Every one of the ... has been invited." as before; with accounts never invited, it offers the button as before.
- [ ] **Step 3:** Pass `none` from each screen when `WPCPM_Mail::everyone()` for its role is empty. The sentences: "No mentor has an account on the site yet.", "No institution has an account on the site yet.", "No sponsor has an account on the site yet." (or the filter wording from Step 1).
- [ ] **Step 4:** Run the three suites and then every `bin/test-*.php` (each exits 0), `php bin/check-references.php`, `php bin/check-spelling.php`.
- [ ] **Step 5: Commit** "Invitations: the Mentors, Institutions and Sponsors cards say when nobody holds the role, where they said every one had been invited".

### Task 2 (theme): a navigation of only the panel-only buttons

**Files:**
- Modify: `inc/partners.php` (the signed-in hook of the two buttons and the navigation filter), `inc/header.php` (the lone-link count, about line 34, and its comment), `style.css` (the comment that says "two buttons").
- Test: `bin/test-partners.php`, `bin/test-header.php`.

**Interfaces:**
- Consumes: the 1.32.9 mechanics: `wpcredits_partners_offers( $kind )`, `wpcredits_partners_acts_for( $kind )`, the class `wpc-nav__panel-only`, the lone-link count that skips the hidden buttons.

- [ ] **Step 1: Failing tests:** a signed-in reader whose navigation would hold nothing visible but the panel-only buttons (an empty saved menu with the levels switched off, or the hooked Partners link deleted) gets no panel-only buttons and no empty navigation landmark; a signed-in reader with Partners (live's state) or a saved menu gets them as 1.32.9 does; a visitor is unchanged; the lone count's skip requires the item to be a `wp-block-navigation-link` as well as panel-only (1.32.9 Minor 6).
- [ ] **Step 2:** Implement at render time, in the navigation filter: leave the panel-only buttons out when no other item of the navigation would show. Do not tie it to the levels switch or to the Partners link alone (the 1.32.9 review rejected both).
- [ ] **Step 3:** Comments: "two buttons" in `inc/header.php` and `style.css` becomes the buttons a reader is offered (a reader who acts for a sponsor or an institution is offered one); "the site's owner" in `inc/partners.php` (about lines 15, 17, 110, 304) becomes "the product owner".
- [ ] **Step 4:** Run every theme `bin/test-*.php` (each exits 0).
- [ ] **Step 5: Commit** "Header: a signed-in reader's Menu panel offers no buttons alone, and the comments say the buttons a reader is offered".

### Task 4 (plugin): a Track Builder question students see but cannot change

Added 28 Oct 2026 after the test on the Docker copy confirmed the certificate code problem (plugin `.superpowers/session-backups/2026-10-28/certificate-code-test.md`): on the three tracks that show a certificate code (live tracks 1228 Foundations Track, 1360 WordPress Credits Intro to Contribution, 1629 Foundations Track (Haaga-Helia)), the code is a plain `text` question; a student can type over it or clear it, and a save from a page drawn before the code arrived (or inside the card's five-minute cache) sends the code cell empty and wipes it, because a save sends every field on the form. The product owner approved the fix ("If it's real, a small fix release would make the box read-only").

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-definition.php` (`QUESTION_PROPERTIES`, `FLAGS`, validation), `includes/modules/class-wpcpm-student-report-form.php` (`render_field()` and `handle_save()`), `includes/tools/class-wpcpm-track-editor.php` and `includes/tools/class-wpcpm-track-editor-screen.php` (the setting in the question editor and the preview), the Track Builder part of the Administrator guide in `docs/sections/` (then `php bin/build-docs.php`).
- Test: the Student Report Card's and the Track Builder's suites (find them with `grep -ln "handle_save\|Track_Definition" bin/test-*.php`).

**Interfaces:**
- Produces: a boolean question property `read_only` (default false) in the track definition, kept by compile and publish; the question editor's checkbox labeled "Filled in by the program: students see it but cannot change it".

- [ ] **Step 1: Failing tests**, the certificate test's cases as assertions, for a `read_only` text question: (a) a typed value is not saved; (b) a cleared box is not saved; (c) a save from a page drawn before the value arrived does not send the column at all; (d) an old value does not overwrite a newer one; (g) a hand-made POST naming only that question is ignored. And: questions without the flag save exactly as before; the definition accepts `read_only` true or false and refuses anything else; the editor's checkbox round-trips; the Track Builder preview draws the question without an editable field.
- [ ] **Step 2: Implement.** `handle_save()` skips a `read_only` question before it reads the posted value, so nothing a student posts reaches the column, whatever the page or the request. `render_field()` draws it with its label and help and the stored value in a field the student can select and copy but not edit (a `readonly` input, or the plugin's existing read-only drawing if there is one), and with no value just the label and help; its name is never part of what the form posts. If an Administrator or mentor path writes a student's answers, check it and keep it writing as before (the flag is about the student's own card); say what you found.
- [ ] **Step 3:** The Administrator guide's Track Builder part says what the setting does and that the certificate code question uses it. Rebuild the guides.
- [ ] **Step 4:** Every `bin/test-*.php` exits 0; `php bin/check-references.php`; `php bin/check-spelling.php`.
- [ ] **Step 5: Commit** "Track Builder: a question students see but cannot change, so a save never wipes a certificate code".

After the release (the coordinator, on the product owner's yes): tick the setting on the code question of tracks 1228, 1360 and 1629 on live and republish each, then check a student's card on those tracks shows the code row without an editable field.

### Task 5 (plugin): a link to a folded part of the Institution Dashboard opens it

Added 28 Oct 2026 on the product owner's yes, after an Administrator could not find "Download a sample list": it sits in the Students section's "Enroll students" part, a `<details>` folded by default inside `section#wpcpm-import` (`includes/modules/class-wpcpm-institution-import-form.php` about line 125), and a link to `#wpcpm-import` scrolls to the closed part and leaves it closed.

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (enqueue the dashboards' opener, `WPCPM_Dashboard_Card::OPENER`, registered in `includes/class-wpcpm-dashboard-card.php` about line 72), `assets/js/dashboard-open.js` only if it does not already open a disclosure inside the section a fragment names (read its header first: it opens the disclosure the fragment names, the one a section it names opens with, and every disclosure around the target), and the Institution guide's mention of the sample (`docs/sections/51-institution-students.md`, Enrolling students step 3) if a link to `#wpcpm-import` is worth naming there.
- Test: the opener's suite (find it with `grep -ln "dashboard-open" bin/test-*.php bin/js/*`), and the Institution Dashboard's suite for the enqueue.

- [ ] **Step 1: Failing tests:** the Institution Dashboard enqueues the opener; with the fragment `#wpcpm-import`, the opener opens the "Enroll students" disclosure (inside the section the fragment names) and moves the focus as it does on the other two dashboards; a fragment naming nothing on the page changes nothing; the Sponsor and Administrator Dashboards behave as before.
- [ ] **Step 2: Implement** the enqueue, and the opener change only if Step 1 shows it is needed, keeping its header true for all three dashboards.
- [ ] **Step 3:** Every `bin/test-*.php` exits 0 (and any node-run suite the opener has); `php bin/check-references.php`; `php bin/check-spelling.php`.
- [ ] **Step 4: Commit** "Institution Dashboard: a link to a folded part opens it, so the sample list's link is found".

### Task 3 (coordinator): versions, trial and release

Runs after Tasks 4 and 5 (added 28 Oct; both ship in 1.123.46).

- [ ] Plugin 1.123.46 and theme 1.32.10 in every place, changelogs, the plugin's translation template.
- [ ] Docker trial: a code question with the new setting on a Docker copy track (the copy holds only the four original tracks: build one through `WPCPM_Track_Store::create()` and `publish()`, as the certificate test did), the three cards with no account of the role (on a snapshot), and the Menu panel as a signed-in reader with live's navigation and with an empty one, at 375 and 1100 pixels.
- [ ] Release script check, build and scan for each; mirror and deploy only on the product owner's yes, the plugin first, then a re-scan and the theme.
