# Plugin 1.123.29: Institutions release 1, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The institution application asks its five new questions and keeps the answers on the site. Administrators get linked Pipeline names, one type-to-find field and honest counts, a Revoke for every agreement in force, and an institution's history on its page.

**Architecture:** The application's field list gains site-only fields that are stored apart from the Airtable answers. A new `WPCPM_Institution_Record` keeps them per institution after approval. The Pipeline tab, the Agreements tab and the Institution Dashboard each gain one piece, built from helpers that exist (`WPCPM_Dashboards::render_combo()`, `WPCPM_Institution_Audit::entries_for()`, `handle_revoke()`, `WPCPM_Typed_Text`). No new option, setting or email.

**Tech Stack:** PHP 7.4+ WordPress plugin, prefix `WPCPM_`. The no-WordPress suites `bin/test-*.php`: run each from the plugin root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0.

**Spec:** `docs/specs/2026-10-24-institutions-features-design.md`, section 3 (release 1), with sections 1, 2 and 8. Approved by the product owner on 24 October 2026.

**Repository:** /Users/maciejpilarski/GitHub/wpcredits-program-manager, worktree `.worktrees/plugin-1-123-29`, branch `release/1.123.29-institutions-release-1`, from `main` at the commit that adds this plan (1.123.28 is live).

## Global Constraints

- **Leave the release files alone.** Do not touch the version, `readme.txt`'s changelog or the `.pot`: the coordinator does those.
- **Words:**
  - Never an em or en dash, anywhere.
  - US English, "Administrators", full product names.
  - No person's name in code, comments, docs, tests or commit messages: roles only.
  - Test addresses use `example.test`.
- **Copy:** every user-facing string quoted in this plan is used verbatim.
- **Code style:** match the surrounding code: docblocks that say why, escaping at output, a translators comment on every placeholder.
- **phpcs:** no new errors or warnings (60 warnings and no errors today).
- **No Airtable column is added or written** by anything in this plan. Approval's Airtable create cells stay exactly as they are today.
- **Tests first,** in the repository's style, each shown failing before its fix.
- **Before each report,** run all of these and quote their last lines, with `PATH="/private/tmp/claude-501/-Users-maciejpilarski-GitHub/25b9f120-1b7e-4cf2-9739-9a4891fc924e/scratchpad/phpcs-1g:$PATH"`:
  - the battery (every `bin/test-*.php`);
  - `php bin/check-references.php`;
  - `php bin/check-spelling.php`;
  - `php bin/test-fixtures.php`;
  - `bash bin/check-standards.sh`;
  - `php bin/check-dead-annotations.php`.
- **Hands off:** no subagents, no network, no ssh, no docker, no stash, no push.
- **Commits:** one or more per task. Each is a subject line plus one plain paragraph, ends with the repository's Co-Authored-By trailer, and is made via `git commit -F`.
- **Docs:** each task updates the docs/sections paragraphs that describe what it changes, in the same commit. `php bin/build-docs.php` still runs, and the suites that read its output pass.

## Review Focus

1. **An application submitted before this release** has no site-only answers. It approves exactly as today and creates no institution record (Task 2 pins it).
2. **A tampered post:** a language not on the list, a title with no signer, or a page address with a `javascript:` scheme. It is cleaned or refused, never stored as typed (Task 1 pins it).
3. **A member opening their own Institution Dashboard** never receives the For Administrators heading or any history text in the HTML. An Administrator viewing the same institution through the switcher does (Task 5 pins it).
4. **A stage stored with surrounding space, or one the list does not know** ("SPAM ", "Paused"). The first is left out of the counts like "SPAM"; the second is counted (Task 3 pins it).
5. **Revoke pressed twice from two open tabs.** The second press is refused by the state check and sends no second email. A refused short note comes back in its box (Task 4 pins it).

---

### Task 1: The application's five new fields, kept on the site

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-application.php` (`fields()`, `groups()`, the `choices` type's clean and render, `problems()`/`add_required()`, `store()`)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`render_application_answers()`)
- Modify: `assets/css/application.css` only if the new group needs a rule its siblings have
- Test: `bin/test-institution-application.php`, `bin/test-institutions-screen.php`, `bin/test-administrators-dashboard.php` (the "Read the application" answers)

**Interfaces:**
- Produces:
  - `WPCPM_Institution_Application::META_SITE = '_wpcpm_app_site'`, the site-only answers keyed by field key;
  - `WPCPM_Institution_Application::site_answers( int $application_id ): array`, the stored site-only answers, `array()` when none;
  - `WPCPM_Institution_Application::site_fields(): array`, the field specs of `fields()` that carry `'site' => true`, in form order.
- A field spec may carry `'site' => true` (never sent to Airtable) and `'choices' => string[]` (the list a `choices` field offers). The internship field passes `self::internship_choices()`, and its "Other" rule stays its own.

- [ ] **Step 1: Write the failing tests.** In `bin/test-institution-application.php`, with `ck()`:
  - **The new specs.** `fields()` holds these five, `'site' => true`, labels verbatim:
    - `contact_role`: group `contact`, placed right after `Contact Email`, label "Their role at the institution", type `text`, required, maxlength 120, help "For example: internship coordinator, or head of the computer science department."
    - `student_languages`: group `program`, last in its group, label "In which languages do your students study?", type `choices`, required, `'choices' === WPCPM_Mentor_Application_Form::languages()`, help "Tick every language your students study in."
    - `signer_name`: group `agreement`, label "Who will sign the agreement for the institution?", type `text`, not required, maxlength 120, help "Somebody who can commit the institution, such as a dean. Leave it empty if you do not know yet."
    - `signer_title`: group `agreement`, label "Their title", type `text`, not required, maxlength 120, help "For example: Dean of the Faculty of Engineering."
    - `credits_page`: group `agreement`, label "Your page about WordPress Credits", type `url`, not required, maxlength 200, help "The agreement asks the institution to publish a page about the program on its website. If it is up already, give its address; if not, leave this empty."
  - **The groups.** `groups()` is `about, contact, program, agreement, more, consent`, with `'agreement' => 'The agreement'`. The form draws 6 fieldsets and 18 fields.
  - **Storing.** A valid submit with all five stores them under `META_SITE` and none of them under `META_FIELDS`. `META_FIELDS` keeps exactly today's 11 columns.
  - **Requiredness.**
    - A signer named with no title refuses the title with "Please fill this in.".
    - No signer, no title and no page submits.
    - No language ticked refuses with "Please fill this in.".
  - **Cleaning.**
    - A language not on the list is dropped, and the field is refused when nothing is left.
    - `credits_page` is cleaned as Website is (`example.edu/credits` gains its scheme).
    - A `javascript:` address is refused with "That answer could not be read. Please try it again.".
  - **The fixture check** (every column exists in `bin/fixtures/institutions-table-fields.json`) runs over the fields without `'site'`, and fails for one that is missing.
  - **Existing pins:** every count and order pin of the suite is updated to the new shape, among them 13 to 18 fields, 5 to 6 fieldsets, the hash map and the requiredness table.
  - **The answers tables.** In `bin/test-institutions-screen.php`, the queue's answers table prints 18 rows, the site-only ones without a `<code>` column name. The same holds for the Administrator Dashboard's "Read the application" in its suite.
- [ ] **Step 2: Run them red:** `php bin/test-institution-application.php`, `php bin/test-institutions-screen.php` and `php bin/test-administrators-dashboard.php` fail on the new checks.
- [ ] **Step 3: Implement.**
  - `store()` splits the cleaned values: `site` fields go to `META_SITE`, the rest to `META_FIELDS` as now.
  - The `choices` type reads `$spec['choices']`.
  - The title's conditional requiredness lives where the Comments rule does.
  - `render_application_answers()` reads a site field's value from `site_answers()` and prints no column name for it.
  - `delete_all()` and the purge already delete the post with its meta: confirm that the suite's purge check sees `META_SITE` go.
- [ ] **Step 4: Run green,** then the battery and the checks of the Global Constraints.
- [ ] **Step 5: Docs** (the form's description in docs/sections/31-admin-settings.md and 30-admin-wpadmin.md), then commit.

### Task 2: The institution's record on the site

**Files:**
- Create: `includes/modules/class-wpcpm-institution-record.php`
- Modify:
  - `includes/modules/class-wpcpm-institution-approval.php` (`approve()`, after `account()` succeeds and before the state becomes approved);
  - `wpcredits-program-manager.php` and `uninstall.php` (require the file);
  - `includes/modules/class-wpcpm-institutions.php` (`init` registers it, `uninstall()` calls its `delete_all()`);
  - `includes/class-wpcpm-privacy-guard.php` (`private_post_types()`).
- Test: create `bin/test-institution-record.php`; extend `bin/test-institution-approval.php`, `bin/test-uninstall.php`, `bin/test-privacy-guard.php`

**Interfaces:**
- Consumes: `WPCPM_Institution_Application::site_answers()` (Task 1).
- Produces:
  - `WPCPM_Institution_Record::POST_TYPE = 'wpcpm_inst_record'`;
  - `WPCPM_Institution_Record::get( string $record_id ): array`, the kept answers by field key, `array()` when none;
  - `WPCPM_Institution_Record::fill_empty( string $record_id, array $answers ): bool`;
  - `WPCPM_Institution_Record::delete_all(): void`.

- [ ] **Step 1: Write the failing tests.** In `bin/test-institution-record.php`:
  - `fill_empty()` on an unknown institution creates one private post (title and meta `_wpcpm_inst_record` the record ID, answers in `_wpcpm_inst_answers`) and returns true.
  - A second call fills only keys that are empty and keeps the others; it returns false when it changed nothing.
  - `fill_empty()` with only empty answers writes no post and returns false.
  - A record ID not shaped `rec` plus 14 letters or digits returns false and writes nothing.
  - `get()` reads back what was kept.
  - `delete_all()` removes every row.

  In `bin/test-institution-approval.php`:
  - Approval of an application with site answers calls `fill_empty( $record, $site_answers )` once, on both the created and the joined path. The Airtable create cells are byte for byte today's 15.
  - **Review Focus 1:** an application with no `META_SITE` meta approves as today and leaves no `wpcpm_inst_record` post.
  - A second press of Approve is refused as now and changes no record.

  The uninstall suite sees the type deleted. The privacy guard suite holds it to `private`.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** the class, registered on `init` the way `WPCPM_Institution_Request::register_post_type()` registers its type (not public, no UI, no REST). Meta is never autoloaded. The answers are slashed on write (`wp_slash`).
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Commit.**

### Task 3: The Pipeline tab, and the two long selects

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php` (`render_pipeline()`, `render_pipeline_row()`, `render_member_gap()`, `render_routing_gaps()`, the consent counts, `render_link_form()`, `link_choices()`)
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php` (`render_draft_picker()`)
- Modify: `assets/css/admin.css`, `assets/css/emails.css`, `includes/tools/class-wpcpm-emails.php` (the combobox rules move, see Step 3)
- Test: `bin/test-institutions-screen.php`, `bin/test-semester-report.php`, `bin/test-unlinked-link.php`, `bin/test-emails-screen.php`

**Interfaces:**
- Produces:
  - `WPCPM_Institutions::UNCOUNTED_STAGES = array( 'SPAM', 'Not Moving Forward', 'Revisit Later', '' )`;
  - `WPCPM_Institutions::counted_rows( array $rows ): array`, the rows whose trimmed stage is not in that list.

- [ ] **Step 1: Write the failing tests** in `bin/test-institutions-screen.php`, against the seed index (104 rows: 14 Not Moving Forward, 2 SPAM, 1 Revisit Later) plus one row staged "SPAM " and one staged "Paused":
  - **The total.** The card total and the stage counts printed above the line are over `counted_rows()`: the total is 88 for those 106 rows (the 17 seed rows in the three stages and the "SPAM " row left out, "Paused" counted; Review Focus 4).
  - **The other counts on the tab are over counted rows:** Confirmed with no agreement, no live member, routing gaps and consent. The Sync and storage tab's member count is unchanged.
  - **The four groups** print after the line "Not counted above", each as a closed `<details>` whose summary is the stage label and its count. Their rows draw as before.
  - **Names.** Each Pipeline name is `<a href="{page}?wpcpm_institution_view={record}">`. With no published Institution Dashboard (`page_url()` returns '') the names are plain and the field below is not drawn.
  - **The field.** Above the groups, a `<form method="get">` to the dashboard page holds `render_combo()`'s markup:
    - id `wpcpm-pipeline-find`, name `wpcpm_institution_view`, label "Find an institution";
    - options for all 106 rows, keyed by record ID, reading "Name, City (Stage)" (city and stage left out when empty), sorted by name;
    - a submit button "Open its page".
    - Any query arguments the page URL carries go as hidden inputs.
  - **The draft picker.** In `bin/test-semester-report.php`, `render_draft_picker()` draws the institution choice with `render_combo()` (id `wpcpm-draft-institution`, name `institution`, label "Institution") over the same options as today.
  - **The Reconciliation picker.** In `bin/test-unlinked-link.php`, each `render_link_form()` draws `render_combo()` with id `wpcpm-link-<students record>` and name `wpcpm_institution`, and `link_choices()` holds no SPAM row. Two forms on one page carry distinct ids.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
  - The combobox rules the Emails tool keeps in `emails.css` (its lines 35 to 125, under `.wpcpm-emails`) move to `admin.css` under the wrapper class `.wpcpm-admin-combo`. That class wraps the Emails tool's filter field and the three new fields, and `emails.css` keeps none of them.
  - `bin/test-emails-screen.php` and its harness `bin/js/emails-field.js` still pass. A new check reads both stylesheets and finds the rules only in `admin.css`.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Pipeline paragraph, docs/sections/30-admin-wpadmin.md around line 371), then commit.

### Task 4: Revoke an agreement from the Agreements tab

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php` (`render_tab_agreements()` gains `render_agreements_in_force()`)
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`handle_revoke()`; a `TYPED_REVOKE` box like `TYPED_RETURN`; `kept_revoke_note( int $post_id ): ?string` like `kept_return_note()`; the audit row's data)
- Modify: `includes/modules/class-wpcpm-institution-panel.php` (the accept dialog's two strings, lines 718 and 719)
- Test: `bin/test-institution-agreement.php`, `bin/test-institutions-screen.php`, `bin/test-institution-panel.php`

**Interfaces:**
- Produces: the `agreement_revoke` audit row's data carries `'note' => <the note as stored>`. Task 5 reads it.

- [ ] **Step 1: Write the failing tests.**
  - **The new card.** In `bin/test-institutions-screen.php`, the Agreements tab draws a card headed "Agreements in force", after "Agreements on file". It has one row per institution whose `WPCPM_Institution_Agreement::summary()` state is `accepted` or `on_file`, sorted by name. Each row holds:
    - the name, linked as Task 3 links it;
    - "Accepted on the site" or "Recorded on file", with the day it was accepted (`WPCPM_Dates::day()`);
    - a closed `<details>` whose summary is "Revoke this agreement".
  - **The row's form** posts `action=wpcpm_agreement_revoke`, nonce `wpcpm_agreement_revoke_<agreement post>`, `wpcpm_agreement_post=<agreement_id>` and `data-wpcpm-once`. Its textarea:
    - name `wpcpm_agreement_note`, `minlength="20"`, `maxlength="2000"`, required;
    - label "Why it is revoked, in your own words";
    - the description line "Every member of this institution receives this note by email.".
  - **The button** is "Revoke this agreement", with `data-wpcpm-confirm` reading "Revoke the agreement with %s? Its members keep their accounts but reach only the agreement panel, pending invitations are cancelled, and every member receives your note by email." (`%s` the institution's name).
  - **The empty card** says "No institution has an agreement in force." when there are none. No row is drawn for a generated, submitted, returned or revoked agreement.
  - **Keeping the note.** In `bin/test-institution-agreement.php`:
    - a refused revoke (a note under 20 characters, or an Airtable failure) keeps the typed note in the `TYPED_REVOKE` box;
    - the row's textarea is drawn with it, and its `<details>` open;
    - a successful revoke forgets it.
  - **The audit row** of a revoke carries `data['note']`.
  - **Review Focus 5.** A second revoke of the same post, as from a second tab, is refused by the state check: no Airtable write, no email, no second audit row.
  - **The accept dialog.** In `bin/test-institution-panel.php`, both strings end "You can revoke it later on the Institutions screen, in the Agreements tab." and none says "from here".
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** `render_agreements_in_force()` builds the rows from the index and `summary()`, and leaves the handler's order of checks as it is.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Agreements tab paragraph in docs/sections/30-admin-wpadmin.md, and the Administrator guide's agreements part), then commit.

### Task 5: The For Administrators section on the institution's page

**Files:**
- Create: `includes/modules/class-wpcpm-institution-history.php`
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`render()`: after the sponsor events and before the read line, only when `$can_manage`)
- Modify: `wpcredits-program-manager.php` (require the file); `assets/css/institution.css` only for what no existing rule covers
- Test: create `bin/test-institution-history.php`; extend `bin/test-institutions-dashboard.php`

**Interfaces:**
- Consumes:
  - `WPCPM_Institution_Record::get()` (Task 2);
  - `WPCPM_Institution_Application::site_fields()` (Task 1), for labels and order;
  - `WPCPM_Institution_Audit::entries_for( $record, 101 )`;
  - the `note` in the `agreement_revoke` data (Task 4).
- Produces:
  - `WPCPM_Institution_History::render( string $record_id ): string`;
  - `WPCPM_Institution_History::sentence( array $entry ): string`;
  - `WPCPM_Institution_History::who( array $entry ): string`.

- [ ] **Step 1: Write the failing tests.** In `bin/test-institution-history.php`:
  - **The section.** `render()` returns one section: the heading "For Administrators", the line "Only Administrators see this section.", then two folded cards in the shape the dashboard's cards have (a `<section>` with the card class around a closed `<details>`).
  - **"From the application"** lists each kept answer under its form label, in form order, languages joined by ", ". It says "Nothing kept from the application." when `get()` is empty.
  - **"History"** lists the entries newest first. Each row holds:
    - `WPCPM_Dates::moment( time )`;
    - `who()`: the actor's display name, "The site" when the actor is 0, "A removed account" when the user is gone;
    - `sentence()`.
  - **The limit.** With 101 entries, 100 are drawn, then "Showing the latest 100.". With none, it says "Nothing has been recorded for this institution yet.".
  - **Plain sentences.** For every institution kind the plugin writes (the suite lists the 30 kinds as constants), `sentence()` is a non-empty sentence that holds no record ID (`/rec[A-Za-z0-9]{14}/`), no kind slug and no meta key, including `student_linked`, whose stored message is empty.
  - **The revoke row** reads its sentence, then "Note: " and the note.

  In `bin/test-institutions-dashboard.php` (**Review Focus 3**):
  - An Administrator's page, own or through the switcher, holds the section once, after the modules.
  - A member's page holds neither "For Administrators" nor any entry's text.
  - A locked account's page holds neither.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
  - `sentence()` returns the stored message when it is a plain sentence, and otherwise the words from one map keyed by kind, as `summary_label()` keeps agreement states.
  - Every string is escaped at output.
  - The section is never part of an export or a REST answer.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Administrator guide's Institution Dashboard part), then commit.

### Task 6 (coordinator): version, trial, deploy

- [ ] **The release files:** version 1.123.29 in the plugin header, `WPCPM_VERSION` and the readme's Stable tag; the changelog entry; the `.pot`.
- [ ] **Docker:** a snapshot first, then the install. Then check:
  - a test application with the new answers, read in the queue;
  - the Pipeline links, field and counts;
  - the Agreements in force card, with a refused revoke and its note kept;
  - the For Administrators section as an Administrator and as a member.

  Then restore the snapshot and reinstall 1.123.28.
- [ ] **Deploy and mirror** only on the owner's yes. The release republishes page 560.
