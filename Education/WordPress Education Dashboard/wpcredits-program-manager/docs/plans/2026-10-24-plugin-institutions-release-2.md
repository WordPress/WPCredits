# Plugin: Institutions release 2, schools serve themselves, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A school corrects its own details, checked by an Administrator where the owner asked, and corrects a student's details from the student card; the notes card and the school side of "Ask for a mentor" go; the import gets a sample list; the Institution guide becomes a site page; the history reads in today's words.

**Architecture:** A new `WPCPM_Institution_Details` holds the details' fields, cleaning, change list and the one write path (Airtable, then the index, then the institution's record). `WPCPM_Institution_Request` gains a details kind with its own raise, decision and withdrawal. A new card class draws the read view and the two forms in the "Representatives and agreement" module. The rest are changes in place.

**Tech Stack:** PHP 7.4+ WordPress plugin, prefix `WPCPM_`. The no-WordPress suites `bin/test-*.php`: run each from the plugin root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0.

**Spec:** `docs/specs/2026-10-24-institutions-release-2-design.md` (approved by the product owner on 24 October 2026), with release 1's `docs/specs/2026-10-24-institutions-features-design.md`. The two chosen mockups are in `.superpowers/session-backups/2026-10-24/institutions-release-2-mockups/`: `school-card-chosen-B.html` for the card, `administrator-decision-chosen-A.html` for the decision. They are private and never mirrored.

**Repository:** /Users/maciejpilarski/GitHub/wpcredits-program-manager.
- **Worktree:** `.worktrees/plugin-institutions-release-2`, on branch `release/<version>-institutions-release-2`, from `main` at the commit that adds this plan.
- **If Abilities and MCP, Phase A (1.123.30) is merged first,** start from main after it.
- **The version** is the next free number when the build starts.

## Global Constraints

- **Leave the release files alone.** Do not touch the version, `readme.txt`'s changelog or the `.pot`: the coordinator does those.
- **Words:**
  - Never an em or en dash, anywhere.
  - US English, "Administrators", full product names.
  - No person's name in code, comments, docs, tests or commit messages: roles only. Test addresses use `example.test`.
- **Copy:** every user-facing string quoted in this plan is used verbatim.
- **Code style:** match the surrounding code: docblocks that say why, escaping at output, a translators comment on every placeholder.
- **phpcs:** no new errors or warnings (60 warnings and no errors today).
- **No new Airtable column.** The only Airtable writes added are the PATCH of an institution's Name, Website, City, Country (a list of one Countries record ID), Contact Person and Contact Email, through `WPCPM_Airtable::update_records( $settings['institutions_table'], ... )`, with the column names `WPCPM_Institutions_Sync::fields()` gives.
- **No tracked `.csv`, `.json`, `.jsonl` or `.html` file may hold an email address.** The public repository's privacy guard refuses one.
- **Tests first,** in the repository's style, each shown failing before its fix.
- **Before each report,** run all of these and quote their last lines, with `PATH="/private/tmp/claude-501/-Users-maciejpilarski-GitHub/25b9f120-1b7e-4cf2-9739-9a4891fc924e/scratchpad/phpcs-1g:$PATH"`:
  - the battery (every `bin/test-*.php`);
  - `php bin/check-references.php`;
  - `php bin/check-spelling.php`;
  - `php bin/test-fixtures.php`;
  - `bash bin/check-standards.sh`;
  - `php bin/check-dead-annotations.php`.
- **Hands off:** no subagents, no network, no ssh, no docker, no stash, no push.
- **Commits:** one or more per task, on the release branch only. Each is a subject line plus one plain paragraph, ends with the Co-Authored-By trailer naming your own model, and is made via `git commit -F`.
- **Docs:** each task updates the docs/sections paragraphs that describe what it changes, in the same commit.

## Review Focus

1. **The program team changed a checked detail in Airtable while a request waits.** The Administrator's table shows the newer value as "On record now" (read from the index at draw time), and Approve writes the asked value (Task 2 pins it).
2. **A second account of the school, or a second tab, asks for a change while one waits.** It is refused with "One change is already waiting for an Administrator." and no second request is made (Task 3 pins it).
3. **Two Administrators decide the same request at once, Approve in one tab and Decline in another.** The second is refused by the state check under the lock: one Airtable write, one email, one audit row (Task 2 pins it).
4. **Airtable refuses a write** (a group 1 save of the contact person, or an Approve). Nothing is written on the site, the typed values come back (or the request stays open), and the sentence says so (Tasks 1, 2 and 3 pin it).
5. **A form posted with nothing changed.** Nothing is saved or sent, no email goes, and the page says "Nothing was changed." (Task 3 pins it).

---

### Task 1: The details: fields, cleaning, changes and the one write

**Files:**
- Create: `includes/modules/class-wpcpm-institution-details.php`
- Modify:
  - `includes/modules/class-wpcpm-institution-record.php` (a setter; a record found in another status is put back to private; the ID check through `WPCPM_Airtable::is_record_id()`);
  - `includes/modules/class-wpcpm-institutions-index.php` (`patch()`);
  - `includes/modules/class-wpcpm-institution-application.php` (both web address answers capped at their maxlength on the server);
  - `includes/mail/templates/institutions.php` and `includes/mail/class-wpcpm-mail-catalog.php` (five templates);
  - `wpcredits-program-manager.php` and `uninstall.php` (require the file).
- Test: create `bin/test-institution-details.php`; extend `bin/test-institution-record.php`, `bin/test-institutions-index.php`, `bin/test-institution-application.php`, `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`

**Interfaces:**
- Produces:
  - `WPCPM_Institution_Details::GROUP_NOW = 'now'` and `GROUP_CHECKED = 'checked'`.
  - `WPCPM_Institution_Details::fields(): array`: key to `array( 'group', 'label', 'source' => 'airtable'|'record', 'column' => <Sync::fields() key>|'' , 'type' )`, in this order:

    | Group | Key | Source |
    |---|---|---|
    | now | `contact_person` | airtable, contact_person |
    | now | `contact_role` | record |
    | now | `signer_name` | record |
    | now | `signer_title` | record |
    | now | `student_languages` | record |
    | now | `credits_page` | record |
    | checked | `name` | airtable, name |
    | checked | `website` | airtable, website |
    | checked | `city` | airtable, city |
    | checked | `country` | airtable, country (a Countries record ID) |
    | checked | `contact_email` | airtable, contact_email |

  - **The labels** of `fields()`, in the same order, are those the read view, the emails and the Administrator's table use:
    - "Contact person";
    - "Their role";
    - "Signs the agreement";
    - "Their title";
    - "Students study in";
    - "Page about WordPress Credits";
    - "Name";
    - "Website";
    - "City";
    - "Country";
    - "Contact's email".
  - `WPCPM_Institution_Details::current( string $record_id ): array`: key to value, from the index row and the institution's record.
  - `WPCPM_Institution_Details::clean( string $group, array $raw ): array{values: array, problems: array}`: the application's rules for the same answer, through the same cleaners.
  - `WPCPM_Institution_Details::changes( string $record_id, array $values ): array`: key to `array( 'from' => ..., 'to' => ... )`, only for values that differ.
  - `WPCPM_Institution_Details::write( string $record_id, array $changes, int $actor, string $ground ): true|WP_Error`. In this order:
    1. one Airtable PATCH for the `airtable` keys, Country as `array( <ID> )`;
    2. then `WPCPM_Institutions_Index::patch()`;
    3. then `WPCPM_Institution_Record::set()` for the `record` keys;
    4. then one audit row, kind `details_saved`, field names only.

    A WP_Error from Airtable writes nothing on the site.
  - `WPCPM_Institution_Details::lines( array $changes, bool $asked = false ): string`: one line per change. A saved change reads "<label>: <from>, now <to>", and an asked one "<label>: <from>, asked to be <to>". An empty value reads "not given". The request email uses the asked form; the saved and approved emails use the other.
  - `WPCPM_Institution_Record::set( string $record_id, array $answers ): bool`: writes the given keys, a blank clearing one.
  - `WPCPM_Institutions_Index::patch( string $record_id, array $fields ): bool`: changes the named keys of one row and keeps the read time.

- [ ] **Step 1: Write the failing tests.**

  **`fields()`, `current()` and `clean()`** (`bin/test-institution-details.php`):
  - `fields()` is the table above, in order.
  - `current()` reads the index and the record.
  - A refused country (one not in `WPCPM_Countries::options()`) gives "Please choose one of the options offered.".
  - An email that is not one gives "That does not look like an email address.".

  **`changes()` and `lines()`:**
  - `changes()` leaves out an unchanged value, and a value that differs only by surrounding space.
  - `lines()` gives "Website: https://ejemplo.edu, now https://uev.edu", and with `$asked` "Website: https://ejemplo.edu, asked to be https://uev.edu".

  **`write()`:**
  - It sends one PATCH holding exactly the changed Airtable columns, Country as a list of one ID, then patches the index, then sets the record, then writes one `details_saved` audit row with field names and no values.
  - An Airtable WP_Error leaves the index, the record and the audit untouched.
  - A change of only record keys makes no Airtable call.

  **The record** (`bin/test-institution-record.php`):
  - `set()` overwrites, and a blank clears.
  - A record post found as trash or as an auto-draft is put back to private when written.
  - The ID check is `WPCPM_Airtable::is_record_id()`.

  **The index** (`bin/test-institutions-index.php`): `patch()` changes only the named keys and keeps `read`.

  **The application** (`bin/test-institution-application.php`): a Website or page answer over 200 characters is refused on the server.

  **The templates** (catalog and templates suites): the five templates below exist, with their placeholders, under the Institutions module.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** The five templates, verbatim (`{...}` are placeholders; paragraphs joined as the file's other templates join them):
  - **`institution-details-saved`**, to "Who is emailed about institutions".
    - Trigger: "When an institution's member saves a change to the institution's contact and agreement details, or an Administrator saves any detail: to the people the setting names."
    - Subject: "[{site_name}] {institution_name} corrected its details"
    - Body: "{actor_name} changed {institution_name}'s details on the Institution Dashboard:" / "{changes}" / "They are saved, and the program's records follow. Nothing waits for you."
  - **`institution-details-request`**, to the same.
    - Trigger: "When an institution asks to correct its name, website, city, country or contact's email: to the people the setting names."
    - Subject: "[{site_name}] {institution_name} asks to correct its details"
    - Body: "{actor_name} asks to correct {institution_name}'s details:" / "{changes}" / "Nothing is changed until an Administrator approves it. Approve or decline it on the Administrator Dashboard: {request_link}"
  - **`institution-details-approved`**, to the asking account.
    - Trigger: "When an Administrator approves an institution's request to correct its details: to the person who asked."
    - Subject: "[{site_name}] Your institution's details are updated"
    - Body: "An Administrator approved the change you asked for, and the program's records now read:" / "{changes}"
  - **`institution-details-declined`**, to the asking account.
    - Trigger: "When an Administrator declines an institution's request to correct its details: to the person who asked."
    - Subject: "[{site_name}] Your request to correct your institution's details"
    - Body: "An Administrator did not make the change you asked for, and wrote:" / "{note}" / "The details on record are unchanged. You can ask again from the Institution Dashboard: {dashboard_link}"
  - **`institution-details-withdrawn`**, to "Who is emailed about institutions".
    - Trigger: "When an institution withdraws its request to correct its details: to the people the setting names."
    - Subject: "[{site_name}] {institution_name} withdrew its request"
    - Body: "{actor_name} withdrew the request to correct {institution_name}'s details. Nothing waits for you."
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Commit.**

### Task 2: The details request and the Administrator's decision

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-request.php`, `includes/modules/class-wpcpm-administrators-cards.php` (the requests card), `includes/modules/class-wpcpm-institutions.php` (queue rows and their labels)
- Test: `bin/test-institution-request.php`, `bin/test-administrators-dashboard.php`, `bin/test-institutions-screen.php`

**Interfaces:**
- Consumes: Task 1's `changes()`, `write()`, `lines()`, `current()` and the four request and decision templates.
- Produces:
  - **Constants:**
    - `WPCPM_Institution_Request::KIND_DETAILS = 'details'` (in `kinds()`, label "A change to its details");
    - `STATE_WITHDRAWN = 'withdrawn'` (`transitions()`: open to done, declined or withdrawn);
    - `META_CHANGES = '_wpcpm_req_changes'`;
    - `ACTION_DETAILS_DECIDE = 'wpcpm_decide_details'` and `ACTION_DETAILS_WITHDRAW = 'wpcpm_withdraw_details'`.
  - **`raise_details( string $institution, array $changes, int $actor_id, string $ground ): int|WP_Error`.** It has no student. One open details request per institution: a second is `WP_Error( 'wpcpm_request_open' )`.
  - **`open_details_for( string $institution ): int`**, the open request's post ID or 0.
  - **`latest_details_outcome( string $institution ): array`**, the last closed details request: `{ state, closed_at, note }`, or `array()`.

- [ ] **Step 1: Write the failing tests.**

  **Raising** (`bin/test-institution-request.php`):
  - `raise_details()` stores the changes as given, state open, kind details, no student. It writes a `request_raised` audit row and emails `institution-details-request` to "Who is emailed about institutions" with a `request_link` to the Administrator Dashboard's requests card.
  - A second raise for the same institution is refused.

  **Approve** (`ACTION_DETAILS_DECIDE`, `decision=approve`), with capability first, then the nonce `wpcpm_decide_details_<post>`, then the state under the request's lock:
  - It calls `write()` with the stored changes and closes the request as done.
  - It writes a `request_resolved` audit row whose data holds the state.
  - It emails `institution-details-approved` to the asking account.
  - Flash: "The records are updated and the school is told.".
  - An Airtable WP_Error leaves the request open, with "Airtable could not be updated, so nothing was changed here either. The request stays open.".

  **Decline** (`decision=decline`):
  - It needs a note of 20 to 2000 characters, or it refuses with "A note of 20 to 2000 characters is needed to decline: the school reads it." and keeps the typed note (`WPCPM_Typed_Text`).
  - It closes the request as declined with the note, writes nothing to Airtable, and emails `institution-details-declined` with the note.
  - Flash: "The request is declined and the school is told.".

  **Withdraw** (`ACTION_DETAILS_WITHDRAW`, by an account the policy allows `ACT_EDIT_DETAILS` for that institution):
  - It closes the request as withdrawn, audits, and emails `institution-details-withdrawn`.
  - Approve or Decline afterwards is refused with "Nothing was changed: the school withdrew this request.".

  **Review Focus 3:** Approve and Decline posted together leave one Airtable write, one email and one audit row. The second is refused by the state check.

  **The Administrator Dashboard's requests card** (`bin/test-administrators-dashboard.php`) draws a details request as mockup A:
  - "<institution name> asks to correct its details", the name linked as the Institutions screen links names;
  - "Asked by <name> on <day>";
  - the waiting line the card's other requests use;
  - a table with the headings "Detail", "On record now" and "Asked for";
  - the note box labeled "A note for the school (required to decline)";
  - the button "Approve and update the records", and the button "Decline".

  **Review Focus 1:** "On record now" is read from the index at draw time, so a value changed there since the request shows.

  **The Institutions screen** (`bin/test-institutions-screen.php`): the queue row labels it "Details to correct" and counts it.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** The new handlers stand beside `handle_resolve()`, which keeps serving the other kinds unchanged.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Commit.**

### Task 3: The school's details card

**Files:**
- Create: `includes/modules/class-wpcpm-institution-details-card.php`
- Modify:
  - `includes/modules/class-wpcpm-institution-policy.php` (`ACT_EDIT_DETAILS`);
  - `includes/modules/class-wpcpm-institutions-dashboard.php` (the people module draws the card first);
  - `assets/css/institution.css` (only what no existing rule covers);
  - `wpcredits-program-manager.php` and `uninstall.php`.
- Test: create `bin/test-institution-details-card.php`; extend `bin/test-institution-policy.php`, `bin/test-institutions-dashboard.php`

**Interfaces:**
- Consumes: Task 1's `fields()`, `current()`, `clean()`, `changes()`, `write()`, `lines()` and the `institution-details-saved` template; Task 2's `raise_details()`, `open_details_for()`, `latest_details_outcome()` and `ACTION_DETAILS_WITHDRAW`.
- Produces:
  - `WPCPM_Institution_Policy::ACT_EDIT_DETAILS = 'edit_details'`, with the manager and member grounds, not in `ungated()`;
  - `WPCPM_Institution_Details_Card::render( string $record_id, array $context ): void`;
  - the actions `wpcpm_save_details` (group now) and `wpcpm_ask_details` (group checked).

- [ ] **Step 1: Write the failing tests.**

  **The policy** (`bin/test-institution-policy.php`): the pinned `grounds()` map gains `edit_details`, and `ungated()` stays the agreement alone.

  **The read view** (`bin/test-institution-details-card.php`):
  - It is a `<section class="wpcpm-institution__card wpcpm-details" id="wpcpm-details">` around an open `<details>` group, as the people card draws.
  - The title is "Your institution's details", and the lead "What the program has on record.".
  - The two small headings are "Contact and agreement" and "Name and address".
  - The rows follow `fields()`, with its labels and order, the languages joined by ", ".
  - An empty value reads "Not given yet".

  **The two disclosures:**
  - Under the first group, "Correct these" with "(saved at once)".
  - Under the second, "Ask to correct these" with "(an Administrator checks first)".
  - Each opens its own form with the application's labels and help.
  - The form buttons are "Save the details" and "Send to an Administrator".

  **Saving and asking:**
  - A member's save of the first group writes at once, emails `institution-details-saved`, and flashes "Your institution's details are saved.".
  - A member's ask of the second group raises the request, and flashes "Sent: an Administrator checks the change, and you will see it here once it is decided.".
  - An Administrator's post of either group writes at once, and emails `institution-details-saved`.

  **While a request waits:**
  - Under each changed row, "Waiting for an Administrator: <asked value>, asked on <day>." with a "Withdraw it" button (a form posting `ACTION_DETAILS_WITHDRAW`).
  - The second group offers no form.
  - **Review Focus 2:** a post of the second group from another account or tab is refused with "One change is already waiting for an Administrator.".

  **After a decision:**
  - The line "Updated on <day>." after an approval.
  - "An Administrator did not make the change you asked for: <note>" after a decline.
  - Nothing after a withdrawal.
  - These show to every account until the next request.

  **Refusals:**
  - A refused field keeps what was typed, through `WPCPM_Typed_Text`, and shows its problem under the field.
  - **Review Focus 4:** an Airtable refusal of a contact person save keeps the typed values and says "Airtable could not be updated, so nothing was saved. Please try again in a while.".
  - **Review Focus 5:** a post that changes nothing says "Nothing was changed." and sends no email.

  **Who gets the card** (`bin/test-institutions-dashboard.php`):
  - The card is first in the "Representatives and agreement" module for a member and for an Administrator.
  - A school whose agreement is not settled gets no card.
  - A stranger gets nothing.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement**, following `school-card-chosen-B.html`, with every shared block's rules as the Institution Dashboard draws them.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Administrator guide's paragraph on requests from institutions), then commit.

### Task 4: The student card, the retired code, and the sample list

**Files:**
- Modify:
  - `includes/modules/class-wpcpm-institution-student-view.php` (draws `WPCPM_Institution_Student_Form::render_form()` folded inside the card; the Mentor rows);
  - `includes/modules/class-wpcpm-institution-request.php` (the school side of "Ask for a mentor" removed: `render_student()`, `render_waiting()`, `render_form()`, `handle_request()` and the `ACTION_REQUEST` hook);
  - `includes/modules/class-wpcpm-mentor-notes.php` (the institution audience reads as no access);
  - `includes/modules/class-wpcpm-institutions.php` (no `WPCPM_Institution_Notes::init()`);
  - `wpcredits-program-manager.php`;
  - `includes/modules/class-wpcpm-institution-import-form.php` (the sample link);
  - `includes/modules/class-wpcpm-institution-import.php` or the export class (the sample's handler).
- Delete: `includes/modules/class-wpcpm-institution-notes.php`, `bin/test-institution-notes.php`
- Test: `bin/test-institution-student-view.php` (or the suite that draws the card), `bin/test-institution-request.php`, `bin/test-institution-import-form.php`, `bin/test-institution-import.php`, the stubs in `bin/test-institutions-accounts.php` and `bin/test-institutions-screen.php`

**Interfaces:**
- Produces: the action `wpcpm_import_sample` (admin-post, `ACT_ADD_STUDENT`, nonced). It sends `students-sample.csv`: the header `name,email,field_of_study,tutor` and the row `Ana Example,ana@example.test,Technology & Engineering,Tutor Example`, made in PHP and never a file in the repository.

- [ ] **Step 1: Write the failing tests.**

  **The student card:**
  - It holds a closed `<details>` with the summary "Correct this student's details" around the existing form, for an account allowed `ACT_EDIT_STUDENT`, and none for one that is not.
  - The Mentor row's blank reads "No mentor yet".
  - The Mentor email row is absent when the student has no mentor.

  **The retired code:**
  - The request class has no `render_student`, `render_form`, `render_waiting` or `handle_request`, and no `wpcpm_request_mentor` hook.
  - An open mentor request already on record is still drawn and closed by the Administrator's card.
  - No class or file named for the institution's notes remains.
  - A mentor note with the institution audience is readable by nobody.

  **The sample list:**
  - The import form's hint is followed by "Download a sample list", linking the nonced handler.
  - The handler refuses an account without `ACT_ADD_STUDENT`.
  - Its body, given to `WPCPM_Institution_Import::parse()` and `clean_rows()`, yields one row with the verdict ok.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Administrator guide's import and student card paragraphs), then commit.

### Task 5: The Institution guide

**Files:**
- Create: `docs/sections/50-institution-dashboard.md`, `docs/sections/51-institution-students.md`, `docs/sections/52-institution-details.md`
- Modify: `bin/build-docs.php` (a fifth guide), `includes/tools/class-wpcpm-handbook-assistant.php` (the institution entry's URL), `docs/README.md` ("Three guides" says five)
- Test: `bin/test-handbook.php`, `bin/test-admin-menu.php`

**Interfaces:**
- Produces:
  - the guide slug `institutions`, built to `docs/institutions.md` and `docs/build/institutions.html`;
  - `WPCPM_Handbook_Assistant::INSTITUTION_GUIDE_PATH = 'institution-guide'`.

- [ ] **Step 1: Write the failing tests.**

  **The build** (`bin/test-handbook.php`):
  - `$guides` gains `institutions` after `administrators`, with:
    - title "Institution guide";
    - lede "Your Institution Dashboard, your students, your details and your Collaboration Agreement.";
    - parts `00-signing-in`, `50-institution-dashboard`, `51-institution-students`, `52-institution-details`.
  - The build writes both outputs.

  **The dashboard's button:**
  - The institution entry's URL is the published page at `institution-guide` when there is one.
  - Otherwise, the handbook URL it has today.

  **The guide's text:**
  - It names no person.
  - It holds no em or en dash.
  - It never says a student will be given a mentor.
  - It describes the roster's "Waiting for a mentor" group as it is.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Write the three parts.** Use the same voice and shape as the sponsor parts, describing the dashboard after Tasks 1 to 4:
  - the identity and help;
  - the agreement panel;
  - the roster and student card;
  - the import and its sample;
  - the semester report;
  - the representatives;
  - the details card and its two groups.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Commit.**

### Task 6: History in today's words, and the carried cleanup

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-history.php`, `includes/modules/class-wpcpm-institutions.php` (Pipeline DRY points)
- Test: `bin/test-institution-history.php`, `bin/test-institutions-screen.php`

- [ ] **Step 1: Write the failing tests.**

  **History** (`bin/test-institution-history.php`):
  - **Member rows** (`member_added`, `member_removed`) are built from their data: the account's display name now ("A former member" when the account is gone), then the way it joined or left in the People card's words (`WPCPM_Institution_People::how_label()` and its reason words).
    - The form is "<name> was added as a member. <how words>." and "<name> is no longer a member. <reason words>.".
    - A stored "(provisioned)" never shows.
  - **Old wording:** any stored message that says "program manager" is read through `words()`.
  - **Requests:** `request_resolved` reads its state.
    - "An Administrator closed a request as handled."
    - "An Administrator declined a request."
    - "An Administrator approved a change to the institution's details."
    - "The institution withdrew a request."

    This holds when its note cannot be shown, too.
  - **New kinds:** `details_saved` reads "The institution's details were corrected." The new request kind's raise reads "The institution asked to correct its details.".
  - **The kinds list both ways:** every kind passed to `WPCPM_Institution_Audit::record(` in `includes/` (found by reading the source) has words, and every listed kind exists.

  **The Pipeline** (`bin/test-institutions-screen.php`):
  - The Find options come from the shared sort (`WPCPM_Dashboards`).
  - `link_choices()` leaves out a stage named by a constant, not the literal.
  - `render_link_form()` uses `ARG_INSTITUTION`.
  - The routing line lists two countries in sorted, joined form again.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Commit.**

### Task 7 (coordinator): version, trial, launch

- [ ] **The release files:** the version (the next free number) in the header, `WPCPM_VERSION` and the Stable tag; the changelog entry; the `.pot`.
- [ ] **Docker:** a snapshot first, then the install. Then check:
  - a group 1 save;
  - a group 2 request, withdrawn, then another declined;
  - Approve stopping at the copy's refused Airtable write;
  - the student card's fold;
  - the sample download;
  - the card at 1280 and 375 pixels, with every gap measured against its neighbors.

  Then restore and reinstall the live version.
- [ ] **For the owner's yes:**
  - the WordPress/WPCredits issue with the handbook's new institutions text;
  - welcome post 753's trimmed text;
  - the guide page on live (slug `institution-guide`, access for the school's members and Administrators), then its ID in the release script's `[guides]`.
- [ ] **Deploy and mirror** only on the owner's yes. The release republishes the guides, the new one included.
