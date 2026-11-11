# Institutions off Airtable: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Administrators run every school's talks on the site and nobody opens the Airtable Institutions grid after the switch, in two releasable plugins: R1 (areas of responsibility, the assigned Administrator, the Tutor level and the tutor list, while Airtable is still the record) and R2 (the record on the site, leads, the Talks card and view, the weekly email, the Airtable copy and the import), then a switch run on the product owner's yes.

**Architecture:** No custom table. The site's record of an institution is what it already keeps, written per change by the site: the row in `WPCPM_Institutions_Index` (the list fields every screen reads) and the private post of `WPCPM_Institution_Record` (every other field, the talks fields among them). Two new private post types hold what is many per school: `wpcpm_inst_note` (notes) and `wpcpm_inst_lead` (leads, until a lead is promoted and gets its Airtable key). All of it is WordPress's own tables, written through `$wpdb` like every name the site holds today. One setting, `institutions_on_site`, turns R2's record on: the sync stops reading the Institutions and Countries tables, every change is copied to Airtable by `WPCPM_Institution_Copy` with an hourly retry, and the Talks card, the Talks view and the weekly email appear.

**Tech Stack:** WordPress plugin PHP (stand-alone suites run as `php bin/test-<name>.php` from the plugin root), vanilla JS (`assets/js/forms.js`, unchanged unless a task says so), the plugin's Airtable client, WP-CLI.

**Spec:** `docs/specs/2026-11-11-institutions-off-airtable-design.md` (approved by the product owner on 11 November 2026). Inventory: `~/Obsidian/WordPress Education/WordPress Education Dashboard/Airtable inventory (11 November 2026).md`. Column fixture: `bin/fixtures/institutions-table-fields.json`.

**Branch and worktree:** the plan and the design are on `design/institutions-off-airtable`. Execution branches and worktrees are the controller's; R2's starts from main after R1 is merged.

## The Institutions table's 45 columns

The fixture's 45 columns (`fields`, byte order) with each one's fate (design 3.1). "Index" is `WPCPM_Institutions_Index`, "record" is `WPCPM_Institution_Record`, "copy" means R2's Airtable copy writes it back after the switch.

| # | Column | Fate | On the site |
|---|---|---|---|
| 1 | Agreement Accepted By | Comes over, written by the agreement path as today | index `agreement.accepted_by` |
| 2 | Agreement Accepted On | Comes over, as above | index `agreement.accepted_on` |
| 3 | Agreement Document | Comes over, as above | record `agreement_document` (R2), agreement option `drive_url` |
| 4 | Agreement Kind | Comes over, as above | index `agreement.kind` |
| 5 | Agreement Signed On | Comes over, as above | index `agreement.signed_on` |
| 6 | Agreement Status | Comes over, as above | index `agreement.status` |
| 7 | Agreement Submitted On | Comes over, as above | index `agreement.submitted_on` |
| 8 | Agreement Template Version | Comes over, as above | index `agreement.template_version` |
| 9 | Anything else you'd like us to know? (U+2019) | Application answer, read-only | record `application_answers.anything_else` |
| 10 | Calendly link (from Person of contact (Team)) (from Countries) | Does not come over: the booking link is the assigned Administrator's account field (R1) | - |
| 11 | City | Comes over, copy | index `city` |
| 12 | Comments | Becomes a note (Ruling below) | `wpcpm_inst_note` |
| 13 | Confirmed on | Comes over; the site stamps it when a school is Confirmed (R2); copy | index `confirmed_on` |
| 14 | Contact Email | Comes over, copy | index `contact_email` |
| 15 | Contact Person | Comes over, copy | index `contact_person` |
| 16 | Country | Comes over, copy (a link to the Countries row) | index `country`, `country_name` |
| 17 | Current Stage | Comes over, copy | index `stage` |
| 18 | Days to Follow-Up | Does not come over (the old follow-up automation's input) | - |
| 19 | Department | Application answer, read-only | record `application_answers.department` |
| 20 | Email (from Person of contact (Team)) (from Countries) | Does not come over (a Countries lookup) | - |
| 21 | Estimated number of students who may be interested | Comes over as the Talks card's estimated students, editable | record `estimated_students` |
| 22 | Feedback | Stays in Airtable (a link to another table) | - |
| 23 | Follow-Up Date | Comes over as the Talks card's follow up on, copy | record `follow_up` |
| 24 | FreeScout (the fixture's "HelpScout", renamed in the base) | Comes over as the help desk link (Ruling below) | record `help_desk` |
| 25 | How do your internships or practices typically work? | Application answer, read-only | record `application_answers.internships` |
| 26 | Last Contact Date | Comes over, kept and shown, copy | record `last_contact` |
| 27 | Linked Onboarding Record | Stays in Airtable (a link) | - |
| 28 | Mentors | Stays in Airtable (a link) | - |
| 29 | Name | Comes over, copy | index `name` |
| 30 | Next Step | Comes over as the Talks card's next step, copy | record `next_step` |
| 31 | Notes | Becomes the first note, dated the import day, "Imported from Airtable" | `wpcpm_inst_note` |
| 32 | Original program manager | Seeds the assignment when no area matches (R1 seed, R2 import for any still unassigned); not kept | record `assigned` |
| 33 | Privacy Policy Compliance | Comes over | index `consent` |
| 34 | Program manager (from Countries) | Does not come over (a Countries lookup) | - |
| 35 | Student Contact name | Application answer, read-only | record `application_answers.student_contact_name` |
| 36 | Students | Stays in Airtable (a link) until the students move | - |
| 37 | Students 2 | Stays in Airtable (a link) | - |
| 38 | Students Reports | Stays in Airtable (a link) | - |
| 39 | Students Reports 2 (from Linked Onboarding Record) | Stays in Airtable (a lookup) | - |
| 40 | Students Reports copy | Stays in Airtable (a link) | - |
| 41 | To Follow Up | Does not come over (the old follow-up automation's input) | - |
| 42 | Tutors | The link stays in Airtable; the tutors themselves come over into each school's list (R1 seed) | record `tutors` |
| 43 | Website | Comes over, copy | index `website` |
| 44 | Why are you interested in offering WordPress Credits to your students? | Application answer, read-only | record `application_answers.why` |
| 45 | Your Email | Application answer, read-only (Ruling below) | record `application_answers.your_email` |

- Ruling: **Comments** (12) becomes a note like Notes, dated the import day and marked "Imported from Airtable (Comments column)". The design names Notes and the record comments but not this column, and its anti-goals say no note may be lost in the import.
- Ruling: **FreeScout** (24, "HelpScout" in the fixture) comes over as the record's help desk link, shown read-only on the Talks card. The design gives leads a help desk link; one field for both keeps a promoted lead's link where the school's is.
- Ruling: **Your Email** (45) is kept with the read-only application answers. It is the old Airtable interest form's applicant address, which no screen uses but which is the applicant's own answer.

## Global Constraints

- Copy: no em or en dash anywhere (plain hyphens); "Administrators" is the one name for the program's staff, and "program manager" appears in no new string, guide or comment; full product names ("Student Report Card", "Institution Dashboard"); US English; no sentence starts with "Please"; nothing links to an Airtable form, in a string, a guide or the changelog.
- Every behavior change gets a check that fails first, in the stand-alone suites run as `php bin/test-<name>.php` from the plugin root. Before each release's last commit: every `bin/test-*.php` suite, `php bin/test-forms-js.php` when forms.js changes, `php bin/check-references.php`, `php bin/check-spelling.php`, `bash bin/check-standards.sh` with `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH"` (0 errors).
- The repo's traps, named again in each task where they bite: a new class needs its `require_once` in `wpcredits-program-manager.php` and in `uninstall.php` (`bin/test-roles.php` checks the two lists agree); suites pin counts and lists (`bin/test-institution-history.php` the audit kinds, `bin/test-fixtures.php` every column the plugin names against the fixtures, `bin/test-settings.php` each scope's keys, `bin/test-mail-templates.php` and `bin/test-emails-templates.php` every email id, `bin/test-privacy-guard.php` the private post types, `bin/test-submit-guard.php` every file that prints `data-wpcpm-confirm`, `bin/test-cli.php` that each command's examples name it); guides are built: edit `docs/sections/*.md` and run `php bin/build-docs.php` twice (the second run changes nothing); a path through WordPress menus is written `Settings > General`; a version bump is everywhere (plugin header `Version`, `WPCPM_VERSION`, readme `Stable tag` and a changelog entry) plus `bash bin/make-pot.sh`.
- Versions: each release takes the next free plugin version when its build starts. The controller reads live, main and every branch first and gives the number to the workers; this plan writes it as `<R1>` and `<R2>` in commit messages. Another session is redesigning the Sponsor and Institution Dashboards in the same files' neighborhood (`class-wpcpm-institutions-dashboard.php`, `class-wpcpm-dashboard-frame.php`, the cards), so each release branch is rebased on main before its last task, and the version is read again then.
- Workers never ssh, never touch the live site, Airtable or Slack, never print the `wpcpm_settings` option, never run `git stash`, and work only in their worktree.
- Dates (design 13): R2 is built after the Institutions testing reports (20 November 2026) and switched in the first week of December, on the product owner's yes.
- The release split's rule: Airtable stays the record in R1. R1 reads and writes Airtable as today, except that nothing routes by the Countries table's "Person of contact (Team)" once the areas are seeded.
- From the design, verbatim where quoted:
  - "**The key stays the Airtable record ID** (`rec...`)": every member link, agreement, request, audit row and the CRM's company link already use it. A lead has no record ID until it is promoted (Task 14).
  - "the site's database connects in latin1. A custom table in utf8mb4 written over that connection stores double-encoded text, so whichever storage is chosen must store names in every script correctly, proven on the Docker copy with names in Polish, Romanian, Arabic and Japanese."
  - "**Who is an Administrator here** (the author's call): an account with the plugin's manage capability, the program's Administrators the site already knows, never every WordPress administrator account. Only they appear in the areas, as assignees, in the notices and in the weekly email." The capability is `WPCPM_Roles::CAP_MANAGE`; the list is `WPCPM_Institutions::managers()`.
  - "One Administrator per country and per region (a save that gives one twice is refused and says which)."
  - A Lead "is never counted, never on a public page, never offered by any form, never written to Airtable (section 9), and it has no accounts."
  - "**Confirmed** is still set by the agreement path (accepted, or recorded as on file), never by hand on the Talks card: the agreement is the gate."
  - The import sends no email: "no assignment email sent by the import". Inviting the imported tutors "is a separate step, only on the product owner's yes, since that mails real people."
  - Anti-goals: an institution changed in two places, or the site reading Airtable after the switch; a school losing access because of the switch itself; a student's or mentor's link to an institution broken in Airtable; a lead counted, shown publicly, offered by a form or written to Airtable; an email to a school, a tutor or a lead that no Administrator chose to send; a note or a follow-up lost in the import; names stored double-encoded.

## Review Focus

1. **A school the Talks card moves out of the active stages and later back in:** its accounts are revoked at once (as the sync's revoke phase does), and on the way back its agreement gate is rebuilt from the site's own record, the Drive link included, so it is never left locked. Pinned in Task 15.
2. **Site mode reads nothing of the Institutions or Countries tables:** not the sync, the queue's duplicate search, the approval's adoption search, the acceptance's stage read or the mentors sync's name lookups. Pinned in Task 10 with a client that records every request.
3. **A copy write that is refused or times out:** the site's change stands, the columns are marked, the hourly retry writes the newest values (not the ones that failed), the Institutions screen names the school until the write goes, and a written retry clears the mark. Pinned in Task 12.
4. **A tutor's hand-made post to a representative's handler** (add or edit a student, change a status, resend an invitation, invite or remove a member, correct details, upload an agreement, arrange the page, export): refused, nothing written. Pinned in Task 6.
5. **The import run twice, or stopped half way and run again:** no note, comment, lead or tutor is added twice, nothing already on the site is overwritten, and no email is sent. Pinned in Task 18.

## Before the tasks (the controller's, read-only)

Before Task 8, read the base's metadata (read-only, through the connector) and commit two fixture changes on the R1 branch, so no worker needs Airtable:

- `bin/fixtures/institutions-table-fields.json`: add to `types` the types read from the live base on 11 November 2026: "Original program manager" `multipleRecordLinks` (to Team Members, `tblUYWUSEcRLJ5BaR`), "Next Step" `singleLineText`, "Follow-Up Date" `date`, "Last Contact Date" `date`, "Notes" `multilineText`, "Comments" `multilineText`, "FreeScout" `multilineText` (the live base has no "HelpScout" column on Institutions any more: it is "FreeScout"; replace the fixture's name), "Your Email" `email`; bump `read`. Ruling (the controller's, from that read): "Original program manager" is a link to Team Members, so Task 8's resolver follows it: it reads the Team Members table (Name, Email) and resolves the linked row's Email to an account, else its Name compared trimmed and case-insensitive with an Administrator's display name, else nothing.
- The Team Members table (`tblUYWUSEcRLJ5BaR`): "Name" `singleLineText`, "Email" `email`, "Calendly link" `url`, "Countries" `multipleRecordLinks`.
- `bin/fixtures/outreach-tracker-fields.json` (new, field names and types only, no values), as read on 11 November 2026: the Outreach Tracker (`tblQssm68GvGEfAEH`): "Ac" `multilineText` (the primary column: the lead's name), "City" `singleLineText`, "Country" `singleLineText` (text, not a link: Task 18 resolves it to a Countries record by name, unresolved names stay as text on the lead), "Introduced to WPCC" `checkbox`, "Introduced to WP Credits" `checkbox`, "Lead Owner" `singleLineText`, "Date of last contact" `date`, "Notes" `richText`, "HS link (where applicable)" `richText` (the help desk link), "Promoted to Institutions" `checkbox` ("Promote to Institutions" is a button, not read). **There is no contact people column**: a lead's contact person and address come over empty, and an Administrator fills them on the Talks card (design decision 9 names contact people; the tracker never held them). The Tutors table (`tbllW9yWhbg1UCHVy`): "Name" `singleLineText`, "Email" `email`, "Institutions Onboarding" `multipleRecordLinks`. Its `_comment` says what it pins, in the style of the other fixtures.

---

# Part 1: Release R1 (areas, assignment, the Tutor level)

### Task 1: Regions, the country map and the resolution rule

**Files:**
- Create: `includes/modules/class-wpcpm-institution-areas.php`; `includes/data/country-regions.php` (returns `array<string, string>`, country name => region key, one line per country)
- Modify: `wpcredits-program-manager.php` and `uninstall.php` (the require, beside `class-wpcpm-institution-tracks.php`)
- Test: `bin/test-institution-areas.php` (new suite, in the style of `bin/test-institution-tracks.php`; the two settings are read through `get_value()` with an empty default until Task 2 registers them)

**Interfaces:**
- Consumes: `WPCPM_Countries::all()`, `::name_of( $record_id )`; `WPCPM_Settings::get_value()`; `WPCPM_Roles::CAP_MANAGE`.
- Produces:
  - `const SETTING = 'institution_areas';` `const SETTING_REGIONS = 'institution_regions';`
  - `public static function regions(): array<string, string>` key => label, in this order: `africa` Africa, `asia` Asia, `europe` Europe, `latin-america` Latin America and the Caribbean, `middle-east` Middle East, `north-america` North America, `oceania` Oceania.
  - `public static function shipped_region( $country_name ): string` from the data file, '' for a name it does not hold.
  - `public static function region_of( $country_id ): string` the override in `institution_regions`, else `shipped_region( WPCPM_Countries::name_of( $country_id ) )`.
  - `public static function countries_by_region(): array<string, array<string, string>>` region key (and '' for "No region") => country ID => name, A to Z.
  - `public static function areas(): array{countries: array<string, int>, regions: array<string, int>}` the stored setting, malformed entries dropped.
  - `public static function administrator_for( $country_id ): int` the country's Administrator, else its region's; an account that no longer holds `CAP_MANAGE` counts as nobody and the region is asked; 0 for none.
  - `public static function areas_of( $user_id ): array{countries: string[], regions: string[]}` and `public static function has_area( $user_id ): bool`.
  - `public static function gaps(): array<string, string>` country ID => name for every country no rule gives an Administrator, A to Z.

- [ ] **Step 1: Write the failing checks** in `bin/test-institution-areas.php`, with a stand-in countries map built from `bin/fixtures/institutions-index-seed.json`'s 196 `countries` and three accounts (11 and 12 holding `CAP_MANAGE`, 13 not):
  - `regions()` is the seven keys and labels above, in that order.
  - Every one of the fixture's 196 country names has a shipped region that is one of the seven keys (the check lists any that does not).
  - Pinned regions: Poland, Romania, Turkey and Cyprus `europe`; Pakistan, Japan, Armenia and Hong Kong / Macao `asia`; Nigeria, Egypt and Western Sahara `africa`; Mexico, Brazil and Jamaica `latin-america`; Saudi Arabia, Israel, Iran and Palestine `middle-east`; United States and Canada `north-america`; Australia and Papua New Guinea `oceania`.
  - `region_of()` follows an override: with `institution_regions` = `array( <Egypt's ID> => 'middle-east' )` Egypt is `middle-east`; a country whose name the map does not hold is ''.
  - Resolution, with areas `countries => array( <Poland> => 11 )`, `regions => array( 'europe' => 12 )`: Poland is 11, Romania 12, Japan 0; with 11's capability taken away Poland is 12; an area naming account 13 resolves as nobody.
  - `areas_of( 12 )` is `regions => array( 'europe' )`, `countries => array()`; `has_area( 13 )` false.
  - `gaps()` holds Japan and not Poland or Romania.
  - `areas()` drops a country key that is not a record ID, a region key not in `regions()` and a user value that is not a positive integer.

- [ ] **Step 2: Run** `php bin/test-institution-areas.php`. Expected: FAIL (class not found).

- [ ] **Step 3: Implement** the class and the data file. Ruling: the shipped map follows the UN geoscheme's continents, with the Americas split and a Middle East carved out of Western Asia: Middle East is Bahrain, Iran, Iraq, Israel, Jordan, Kuwait, Lebanon, Oman, Palestine, Qatar, Saudi Arabia, Syria, United Arab Emirates and Yemen; Turkey and Cyprus go with Europe; Armenia, Azerbaijan and Georgia stay in Asia and Egypt in Africa; Latin America and the Caribbean is every country of the Americas but the United States and Canada. The design leaves the mapping's form to the builder, and every country can be moved on the screen (Task 2). The data file is read once a request, lazily.

- [ ] **Step 4: Run** `php bin/test-institution-areas.php` and `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: regions, the country map and who an institution's country routes to"`.

### Task 2: The Areas section on Settings > Institutions and its save

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-areas.php` (cleaning and drawing)
- Modify: `includes/class-wpcpm-settings.php` (`defaults()`: `'institution_areas' => array( 'countries' => array(), 'regions' => array() )` and `'institution_regions' => array()`; `scopes()['institutions']` gains both; `write()` hands both to the cleaners and refuses through `refuse()`)
- Modify: `includes/class-wpcpm-settings-screen.php` (`render_institution_settings()`: a new section after "Applications and enrollment", drawn by the areas class)
- Test: `bin/test-institution-areas.php`; `bin/test-settings.php` (the `institutions` scope list near line 1869)

**Interfaces:**
- Consumes: Task 1; `WPCPM_Institutions::managers()`; `WPCPM_Dashboards::render_combo()`; `WPCPM_Countries::options()`.
- Produces:
  - `public static function clean( $raw, array $stored ): array|WP_Error` the posted areas as `areas()` holds them, or a refusal: code `wpcpm_areas_twice` for a country added that already has its own Administrator, `wpcpm_areas_not_admin` for an account without `CAP_MANAGE`.
  - `public static function clean_regions( $raw, array $stored ): array` the overrides after a move; a move back to the shipped region removes the override.
  - `public static function render_settings( array $settings ): void`.
  - Posted names: `institution_areas[regions][<region key>]` (user ID or '' for nobody), `institution_areas[countries][<country ID>]` (user ID, or '' to take the country's own Administrator away), `institution_areas[add_country]` and `institution_areas[add_user]`, `institution_regions[move_country]` and `institution_regions[move_region]` ('' for the shipped region).

- [ ] **Step 1: Write the failing checks:**
  - `bin/test-settings.php`: the `institutions` scope is its eleven keys plus `institution_areas` and `institution_regions`; a save of the scope writes those two and no other scope's.
  - `clean()`: regions posted as `array( 'europe' => '12', 'asia' => '' )` keep Europe and drop Asia; a country row posted '' drops it; `add_country` Poland with `add_user` 11 adds it; `add_country` Poland while Poland's row posts 12 is refused with `wpcpm_areas_twice` and the message "Poland is already given to <12's display name>. Change it in its own row, so the country has one Administrator."; a posted user 13 is refused with `wpcpm_areas_not_admin` and "That account is not an Administrator, so the areas were not saved."
  - Through `WPCPM_Settings::save()`: a refused areas post keeps the stored areas, saves the scope's other settings, and `last_refused()` names the sentence.
  - `clean_regions()`: moving Egypt to `middle-east` stores the override; moving it to '' removes it.
  - `render_settings()` draws: the heading "Areas of responsibility" and the lead "Which Administrator a new institution is assigned to: the country's own Administrator first, then the region's."; one select per region, named as above, offering "Nobody" and every Administrator by name, the stored one selected; one row per country with its own Administrator; an "Add a country" row with the country as one combobox (`render_combo()`) and an Administrator select; a "Who has what" list, one line per Administrator with an area ("<name>: Europe; Poland"); a closed fold "Countries by region" listing each region's countries and "No region" when any, with a "Move a country" row (combobox and region select).

- [ ] **Step 2: Run** `php bin/test-institution-areas.php` and `php bin/test-settings.php`. Expected: FAIL.

- [ ] **Step 3: Implement** the cleaners, the settings wiring and the section. Ruling: the screen asks per place (a region's one select, a country's one row) rather than per Administrator, so one Administrator per country and per region holds by construction and the one way to give a place twice is the add row, which is refused and names the place; the design leaves the form to the builder.

- [ ] **Step 4: Run** `php bin/test-institution-areas.php`, `php bin/test-settings.php`, `php bin/test-settings-choices.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: the Areas of responsibility section on Settings > Institutions"`.

### Task 3: The assigned Administrator and the assignment email

**Files:**
- Create: `includes/modules/class-wpcpm-institution-assignment.php` (require in the loader and in `uninstall.php`)
- Modify: `includes/modules/class-wpcpm-institution-application.php` (`META_ASSIGNED`; `store()` around line 2655 writes it and no longer writes `META_MANAGER`; the `STATE_NEW` mail around line 2234)
- Modify: `includes/modules/class-wpcpm-institution-approval.php` (`approve()`, after the record answers are kept)
- Modify: `includes/modules/class-wpcpm-institution-history.php` (`words()`)
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (Institutions area) and `includes/mail/templates/institutions.php`
- Test: `bin/test-institution-assignment.php` (new); `bin/test-institution-application.php`; `bin/test-institution-approval.php`; `bin/test-institution-history.php` (33 kinds); `bin/test-mail-templates.php`; `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: Task 1's `administrator_for()`; `WPCPM_Institution_Record::get()`, `::set()`; `WPCPM_Manager_Booking::url_for()`; `WPCPM_Institution_Audit::record()`; `WPCPM_Mail::send()`, `WPCPM_Mail_Templates::mail()`.
- Produces:
  - `const RECORD_KEY = 'assigned';` (in the institution's record, a user ID) `const LOG_CHANGED = 'assignment_changed';` `const MAIL_ASSIGNED = 'institution-assigned';`
  - `public static function stored_of( $record_id ): int` the stored ID, whoever it is now.
  - `public static function assignee_of( $record_id ): int` the stored ID while that account holds `CAP_MANAGE`, else 0.
  - `public static function contact_of( $record_id ): ?array` `array( 'user_id' => int, 'name' => string, 'email' => string, 'calendly' => string )` for the assignee, null for none.
  - `public static function label( $user_id ): string` the display name, "<name> (not an Administrator now)" once the capability is gone, '' for 0 (the sponsors' `manager_label()` rule).
  - `public static function choices(): array<int, string>` every Administrator, user ID => display name, A to Z.
  - `public static function assign( $record_id, $user_id, $actor, $notify = true ): bool|WP_Error` true when it changed, false when unchanged, an error for a user without the capability (`wpcpm_assign_not_admin`) or a record that could not be kept; its row's ground is `manager`, or `system` for actor 0.
  - `public static function notify( $user_id, $institution_name, $country_name, $link ): bool` the `institution-assigned` email.
  - `public static function page_url( $record_id ): string` the Institution Dashboard with `WPCPM_Institution_Roster::ARG_VIEW` set, at `#wpcpm-details` (the email's `institution_link` for an institution; the queue link stands in for an application).
  - `public static function for_application( $post_id ): int` the application's `META_ASSIGNED`.
  - `WPCPM_Institution_Application::META_ASSIGNED = '_wpcpm_app_assigned'`.

- [ ] **Step 1: Write the failing checks:**
  - `assign( A, 11, 7 )` returns true; `assignee_of( A )` 11; `contact_of( A )` holds 11's name, address and booking link; the same call again returns false and writes no second row.
  - The audit row: kind `assignment_changed`, institution and subject A, actor 7, ground `manager`, `data` `array( 'from' => 0, 'to' => 11 )`, message "<11's name> is now the assigned Administrator."; to 0: "Nobody is assigned now."; history sentence "The institution's assigned Administrator was changed."; `bin/test-institution-history.php` counts 33 kinds.
  - The email: `assign( A, 11, 7 )` sends `institution-assigned` to 11 once, its link `page_url( A )`; `assign( A, 11, 11 )` (to oneself) and `assign( A, 12, 7, false )` send nothing.
  - With 11's capability taken away: `assignee_of( A )` 0, `stored_of( A )` 11, `label( 11 )` "<name> (not an Administrator now)"; `assign( A, 13, 7 )` is refused with `wpcpm_assign_not_admin`.
  - Application: a submission from Poland (area 11) stores `META_ASSIGNED` 11 and no `META_MANAGER`; when it lands as `new` the assignment email goes to 11 with the queue link and `institution-application` goes to nobody; a submission from Japan (no area) stores 0 and sends `institution-application` through `notify_managers()` as today; a `held` or `spam` one sends neither.
  - Approval: an approved application with `META_ASSIGNED` 11 leaves the record assigned to 11 and sends no email; an adopted record that already has an assignee keeps it.
  - Template `institution-assigned`: catalog label "Institution assigned, to the Administrator", audience Administrator, area Institutions; subject "[{site_name}] {institution_name} is assigned to you"; body "{institution_name}, in {country}, is now assigned to you." / "Open it here: {institution_link}" / "You are told because you follow this institution up. Reassign it from its page if somebody else should."; eol "\r\n"; placeholders `site_name`, `institution_name`, `country`, `institution_link`, with `institution_link` required.

- [ ] **Step 2: Run** `php bin/test-institution-assignment.php`, `php bin/test-institution-application.php`. Expected: FAIL.

- [ ] **Step 3: Implement** the class, the application's snapshot and mail, the approval's copy (`assign( $record, for_application( $id ), $manager_id, false )` when `stored_of()` is 0), the template and the history words. Ruling: an application assigned by an area tells its assignee alone, and an unassigned one tells everybody `notify_managers()` tells today; the design's "a school with no matching area is unassigned and every Administrator is told, as today" reads as the matching case telling the assignee only, and the Airtable automation it replaces told one Administrator. Ruling: no assignment email to the Administrator who made the assignment.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-mail-catalog.php` and `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: every institution has an assigned Administrator, told by email"`.

### Task 4: Reassignment on the details card

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-assignment.php` (the section and its handler)
- Modify: `includes/modules/class-wpcpm-institution-details-card.php` (`init()` hooks the handler; `render()` calls the section after `WPCPM_Institution_Tracks::render()`; `messages()` and the flash's `section` read `assignment` as they read `tracks`)
- Test: `bin/test-institution-details-card.php`

**Interfaces:**
- Consumes: Task 3's `assign()`, `stored_of()`, `label()`, `choices()`; `WPCPM_Institution_Policy::decide()`, `GROUND_MANAGER`; `WPCPM_Institution_Details_Card::finish()`.
- Produces: `const ACTION_SAVE = 'wpcpm_institution_assign';` `const FIELD = 'wpcpm_assigned';` `const OUTCOME_ID = 'wpcpm-details-assignment-outcome';` `public static function render( $record_id, $checker, $message = null ): void`; `public static function handle_save(): void`.

- [ ] **Step 1: Write the failing checks:**
  - A member's card draws no "Assigned Administrator" heading, name or form.
  - An Administrator's card draws the subtitle "Assigned Administrator", the label of the stored one or "Nobody yet", and a closed fold "Change who follows it up" holding one combobox (`render_combo()`, name `wpcpm_assigned`, every Administrator plus "Nobody") and a "Save" button, nonce keyed `ACTION_SAVE . '_' . $record`.
  - A post assigning 12 saves, flashes "The assigned Administrator is saved, and they have been emailed." (or "The assigned Administrator is saved." when it is the poster or nobody), and lands on `OUTCOME_ID`.
  - A member's hand-made post with a valid nonce answers 403 and nothing changes; a nonce for another record is refused by `check_admin_referer()`.

- [ ] **Step 2: Run** `php bin/test-institution-details-card.php`. Expected: FAIL.

- [ ] **Step 3: Implement** in the shape of `WPCPM_Institution_Tracks::render()` and `::handle_save()`: the same order (nonce, record shape, `ACT_EDIT_DETAILS` and then `GROUND_MANAGER` required, the school's lock), the same classes.

- [ ] **Step 4: Run** `php bin/test-institution-details-card.php`, `php bin/test-institution-assignment.php`, `php bin/test-institutions-dashboard.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: Administrators reassign an institution from its details card"`.

### Task 5: Every reader of the country routing reads the assignment

**Files (each a one-place change):**
- Modify: `class-wpcpm-institution-application.php` (`mail_applicant()`: the booking link is `WPCPM_Manager_Booking::url_for( for_application() )`; an application stored before R1 keeps its `META_MANAGER` snapshot's link)
- Modify: `class-wpcpm-institution-agreement.php` (`contact_of()` around line 3928: the assignee's address)
- Modify: `class-wpcpm-institutions-dashboard.php` (`contact_block()` around line 1025: the assignee's name, address and booking link)
- Modify: `class-wpcpm-institution-panel.php` (`render_contact()` around line 1444)
- Modify: `class-wpcpm-student-flags.php` (`manager_of()` and `calendly_of()`: through the student's institution's assignee)
- Modify: `class-wpcpm-administrators-cards.php` (`render_application()` around line 2252) and `class-wpcpm-institutions.php` (the queue rows' `contact` around lines 2243, 2277, 2303: the application's or the institution's assignee by name; the pipeline's "no contact" mark around line 4754 and `render_routing_gaps()` around line 4865: `WPCPM_Institution_Areas::gaps()`, mark text "no Administrator" and title "No area of responsibility covers this country.")
- Modify: `class-wpcpm-institution-announce.php` (`manager()`: the assignee's name)
- Modify: `class-wpcpm-manager-booking.php` (the field's description: "The link a sponsor or an institution presses to book a call with you. An https address.")
- Test: `bin/test-institution-application.php`, `bin/test-institution-agreement.php`, `bin/test-institutions-dashboard.php`, `bin/test-institution-panel.php`, `bin/test-student-flags.php`, `bin/test-administrators-dashboard.php`, `bin/test-institutions-screen.php`, `bin/test-institution-announce.php`, `bin/test-manager-booking.php`, `bin/test-countries.php`

**Interfaces:**
- Consumes: Task 3's `contact_of()`, `assignee_of()`, `for_application()`, `label()`; Task 1's `gaps()`.
- Produces: nothing new. `WPCPM_Countries::routing()`, `::contact_of()` and `::manager_name_of()` stay for Task 8's seed alone.

- [ ] **Step 1: Write the failing checks**, one per reader, each with a country whose Countries row names Administrator 11 and an institution assigned to 12: the acknowledgement offers 12's booking link (and `institution-applied-no-call` when 12 has none); the revocation names 12's address; "Your contact at the program" names 12 with address and booking link; the panel says "Your program contact for <country> is <12's address>."; a student of that institution has manager 12 and 12's booking link; the queue row and the Administrator Dashboard's application name 12; the Slack line names 12; the booking field's description is the sentence above. And in `bin/test-countries.php`: no file under `includes/` but `class-wpcpm-countries.php` and `class-wpcpm-institution-seed.php` calls `WPCPM_Countries::routing(`, `::contact_of(` or `::manager_name_of(` (read off the source).

- [ ] **Step 2: Run** those suites. Expected: FAIL.

- [ ] **Step 3: Implement** each repoint. This is what makes "later edits of that column are ignored" true (design 7).

- [ ] **Step 4: Run** the suites of Step 1. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: the assigned Administrator replaces the country's contact everywhere, booking link included"`.

### Task 6: The Tutor level

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-members.php` (`META_LEVEL`, `levels()`, `level_of()`; `attach()` takes the level and writes it, `detach()` deletes it, the audit row's `data` carries it)
- Modify: `includes/modules/class-wpcpm-institution-policy.php` (`GROUND_TUTOR`, `ground_tutor()`, `ground_member()` skips a tutor, `grounds()`)
- Modify: `includes/modules/class-wpcpm-institution-audit.php` (`grounds()` gains `tutor`)
- Modify: `includes/modules/class-wpcpm-institution-invite.php` (`META_LEVEL`; `render_form()` draws the level; `handle_invite()` stores it; `complete_accept()` attaches at it; the mail id by level)
- Modify: `includes/mail/templates/institutions.php` and `includes/mail/class-wpcpm-mail-catalog.php` (two new ids; one sentence in each invitation)
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`render()`: the claim; `$can_move` and the events list for a representative only; `handle_move()` refuses a tutor)
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render_member()` names the level)
- Test: `bin/test-institution-policy.php`, `bin/test-institution-members.php`, `bin/test-institution-invite.php`, `bin/test-invitation-text.php`, `bin/test-institutions-dashboard.php`, `bin/test-institution-people.php`, `bin/test-mail-templates.php`, `bin/test-emails-templates.php`, `bin/test-audit.php`

**Interfaces:**
- Produces:
  - `WPCPM_Institution_Members::META_LEVEL = 'wpcpm_institution_level'`, `LEVEL_REPRESENTATIVE = 'representative'`, `LEVEL_TUTOR = 'tutor'`; `levels(): string[]`; `level_of( $user = null ): string` (`representative` when the meta is absent, which is every account today).
  - `attach( $user_id, $record_id, $how, $actor_id, $invite_id = 0, $level = self::LEVEL_REPRESENTATIVE )`, refusing an unknown level with `wpcpm_member_bad_level`.
  - `WPCPM_Institution_Policy::GROUND_TUTOR = 'tutor'`; `grounds()` gives `ACT_VIEW_ROSTER`, `ACT_VIEW_STUDENT`, `ACT_VIEW_REPORT` and `ACT_VIEW_SEMESTER_REPORT` the grounds `manager, member, tutor`, every other row unchanged.
  - `WPCPM_Institution_Invite::META_LEVEL = '_wpcpm_inv_level'`; posted field `level`; `MAIL_INVITE_TUTOR = 'institution-invite-tutor'`, `MAIL_INVITE_TUTOR_NO_SENDER = 'institution-invite-tutor-no-sender'`.

- [ ] **Step 1: Write the failing checks:**
  - Policy: the expected map above; a tutor of settled A is allowed the four view actions on A with ground `tutor`, and refused every other action on A, and every action on B, with the one refusal; a tutor of an unsettled A is refused the view actions; a representative's decisions are unchanged.
  - Members: `attach( $u, A, 'invited', 7, 0, 'tutor' )` stores `tutor`; `level_of()` of an account with no meta is `representative`; `detach()` deletes the meta; `member_added`'s `data` holds `level`.
  - **Review Focus 4:** a tutor of settled A posts, each with a valid nonce, to the student edit, status, resend, import check and confirm, export, invite, cancel, resend invitation, remove member, add account, details save and ask, agreement upload and withdraw, and `handle_move()`: each is refused (403 or the one refusal) and no meta, option, post or Airtable request is written.
  - Dashboard: a tutor of settled A is drawn the roster, a student's page and the semester reports, and no Move up or Move down, no "Enroll students", no details card, no upload, no invite or Remove, and no sponsors' events; a tutor of unsettled A gets `WPCPM_Dashboards::nothing_to_show()`'s notice.
  - Invitation: the form draws a fieldset "Level" with "Representative" (checked; hint "Adds and changes students, invites colleagues, corrects the details.") and "Tutor" (hint "Sees the students, their Student Report Cards and the semester reports, and changes nothing."); `level=tutor` is stored and the email is `institution-invite-tutor`; an unknown level is refused as `invite-bad-level` with "Choose Representative or Tutor."; acceptance attaches at the stored level; an invitation stored before this release attaches as a representative.
  - Templates: the two new ids, eol "\r\n", `accept_link` required, catalog labels "Invitation to an institution as a tutor" and "Invitation to an institution as a tutor, with no sender named"; the representative invitations carry "You are invited as a representative: you can see and add the institution's students, invite colleagues and correct its details." and the tutor's "You are invited as a tutor: you can see the institution's students, their Student Report Cards and its semester reports, and change nothing."
  - People card: each member's line names "Representative" or "Tutor".

- [ ] **Step 2: Run** the task's suites. Expected: FAIL.

- [ ] **Step 3: Implement.** The dashboard's claim asks `ACT_AGREEMENT` and, refused, `ACT_VIEW_ROSTER`, so a tutor has a claim through the view action alone. Ruling: a tutor may not export, since the design's list of what a tutor sees names no copy out. Ruling: a level is set by the invitation and shown on the People card; changing it is a removal and a new invitation, since the design gives no level control. Ruling: a tutor of a school whose agreement is not settled is shown the nothing-to-show notice, since the gate holds every view action and the locked page's agreement panel is a representative's.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-institution-roster.php`, `php bin/test-semester-report.php` and `php bin/test-mail-catalog.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: the Tutor level on institution accounts, which sees and changes nothing"`.

### Task 7: Each school's tutor list, read by the import's tutor picker

**Files:**
- Create: `includes/modules/class-wpcpm-institution-tutors.php` (require in the loader and `uninstall.php`; hooks from `WPCPM_Institution_People::init()`)
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render()` draws the Tutors section after the members)
- Modify: `includes/modules/class-wpcpm-institution-import.php` (`tutors()` around line 456) and `includes/modules/class-wpcpm-institution-import-form.php` (the hint around line 362)
- Modify: `includes/modules/class-wpcpm-institution-invite.php` (an accepted Tutor invitation adds its address to the list); `includes/modules/class-wpcpm-institution-history.php` (`words()`)
- Test: `bin/test-institution-tutors.php` (new); `bin/test-institution-import.php`; `bin/test-institution-import-form.php`; `bin/test-institution-history.php` (35 kinds)

**Interfaces:**
- Consumes: `WPCPM_Institution_Record::get()`, `::set()`; the school's lock (`WPCPM_Institution_Request::school_lock_name()`); `ACT_MANAGE_MEMBERS`, `ACT_VIEW_ROSTER`.
- Produces:
  - `const RECORD_KEY = 'tutors';` (a list of `array( 'name' => string, 'email' => string )`) `const ACTION_ADD = 'wpcpm_institution_tutor_add';` `const ACTION_REMOVE = 'wpcpm_institution_tutor_remove';` `const LOG_ADDED = 'tutor_added';` `const LOG_REMOVED = 'tutor_removed';`
  - `public static function list_for( $record_id ): array` by name, A to Z.
  - `public static function names( $record_id ): string[]` sorted, each once.
  - `public static function add( $record_id, $name, $email, $actor, $ground ): bool|WP_Error` false when the address is already listed (compared trimmed and lowercase); refusals `wpcpm_tutor_name` (empty or over 100 characters) and `wpcpm_tutor_email`.
  - `public static function remove( $record_id, $email, $actor, $ground ): bool`.
  - `public static function render( $record_id, array $context ): void`; `public static function handle_add(): void`; `public static function handle_remove(): void`.
  - `WPCPM_Institution_Import::tutors( $institution ): string[]`, now `WPCPM_Institution_Tutors::names()`, with no Airtable read and no cache.

- [ ] **Step 1: Write the failing checks:**
  - `add()` then `list_for()` and `names()`; a second add of the same address in another case returns false; the two refusals; `remove()`; each change writes one row (`tutor_added`, `tutor_removed`, the name and address in `data`, message "<name> was added to the tutors." and "<name> was removed from the tutors."); history sentences "A tutor was added to the institution's list." and "A tutor was removed from the institution's list."; 35 kinds.
  - The People card: a representative and an Administrator see the "Tutors" subtitle, each tutor with name and address, a Remove control (with `data-wpcpm-confirm` "Remove <name> from the tutors? Their account, if they have one, stays until you remove it above.", so `bin/test-submit-guard.php` gains this file's row) and an "Add a tutor" form (Name, Email, "Add"); a tutor sees the list and no form; a stranger nothing.
  - Hand-made posts by a tutor or a member of another school are refused and change nothing.
  - The picker offers `names()`; with none listed its hint is "No tutors are listed for your institution yet. Add them in the Tutors section of the People card, and they will appear here."; no Airtable request is made (a client that fails every request, asserted unused).
  - Accepting a Tutor invitation adds the address under the account's display name when it is not listed; accepting a Representative invitation adds nothing.

- [ ] **Step 2: Run** the task's suites. Expected: FAIL.

- [ ] **Step 3: Implement.** Ruling: removing a tutor from the list leaves any account they hold, which is removed from the members above like any member's. The `tutors_table` setting stays, read by Task 8's seed and Task 18's import.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-institution-people.php`, `php bin/test-submit-guard.php`, `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: each school keeps its tutor list on the site, which the enrollment picker offers"`.

### Task 8: The one-off seed: areas, booking links, assignments, tutors

**Files:**
- Create: `includes/modules/class-wpcpm-institution-seed.php` (require in the loader and `uninstall.php`)
- Modify: `includes/class-wpcpm-cli.php` (method `institutions_seed`)
- Modify: `bin/test-fixtures.php` (the Countries check's wording: "the two lookups the areas' seed reads"; the new fixture's columns)
- Test: `bin/test-institution-seed.php` (new); `bin/test-cli.php`; `bin/test-fixtures.php`

**Interfaces:**
- Consumes: the "Before the tasks" fixtures; `WPCPM_Countries::all()` and `::routing()` (the stored map the sync keeps; no new Countries read); `WPCPM_Airtable::fetch_all()`; Task 1's settings; Task 3's `assign( ..., false )`; `WPCPM_Manager_Booking::set()`; Task 7's `add()`.
- Produces:
  - `public static function plan( WPCPM_Airtable $airtable ): array|WP_Error` what would be written: `areas` (country ID => user ID), `booking` (user ID => link), `assign` (record ID => array( user ID, `area` or `original` )), `unassigned` (record IDs with the reason), `tutors` (record ID => list), `unresolved` (each a sentence).
  - `public static function apply( array $plan ): array` counts written.
  - `wp wpcredits institutions_seed [--dry-run]`: the summary lines; `--dry-run` writes nothing.

- [ ] **Step 1: Write the failing checks**, with a stored countries map (Poland's contact 11's address and booking link, Japan's an address with no account), three institutions (A in Poland unassigned, B in Japan with "Original program manager" naming 12 by address, C in Japan naming nobody) and two Tutors rows linked to A:
  - `plan()`: `areas` Poland => 11 and no Japan; `booking` 11 => the link only while 11 has none; `assign` A => (11, `area`), B => (12, `original`); `unassigned` C; `tutors` A => the two; `unresolved` names Japan's address with no account.
  - "Original program manager" (a link to Team Members) resolves through the linked Team Members row: its Email to an account, else its Name compared trimmed and case-insensitive with an Administrator's display name, else nothing (fixture Team Members rows: 12's address; a name only; a name matching nobody).
  - `apply()` writes the areas only while `institution_areas` is empty, and says "The areas are already set on the site, so they were left as they are." otherwise; never replaces a booking link, an assignee or a listed tutor; sends no email (mail capture empty); writes `assignment_changed` and `tutor_added` rows with actor 0 and ground `system`.
  - `--dry-run` writes nothing (options, meta and posts unchanged) and prints the same summary.
  - `bin/test-cli.php`: the command's examples run `institutions_seed`.

- [ ] **Step 2: Run** `php bin/test-institution-seed.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** One `fetch_all()` of the Institutions table with fields Name and "Original program manager", one of the Team Members table (Name, Email), one of the Tutors table with its three fields; the countries come from the stored map. Ruling: the seed gives every country with a contact a country area and leaves the regions empty, since the Countries table names countries only; Administrators move to regions on the screen afterwards. Ruling: the 14 tutors are seeded in R1 rather than at the switch (design 8 says "at the switch"), because R1's picker reads the site's list and would otherwise offer nothing until December; Task 18's import adds any tutor added in Airtable since.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R1>: wp wpcredits institutions_seed, the areas and assignments seeded once"`.

### Task 9: R1's guides and version

**Files:**
- Modify: `docs/sections/31-admin-settings.md` (the Institutions tab: Areas of responsibility), `docs/sections/34-admin-operations.md` (assignment, the email, reassigning, the seed command, the Tutor level, the tutor list), `docs/sections/50-institution-dashboard.md` and `docs/sections/52-institution-details.md` (your contact at the program; inviting a tutor; the Tutors section); then `docs/administrators.md` and `docs/institutions.md` through `php bin/build-docs.php` (twice)
- Modify: `wpcredits-program-manager.php` (`Version`, `WPCPM_VERSION`), `readme.txt` (`Stable tag`, a `= <R1> =` entry), `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`)

- [ ] **Step 1: Rebase and read the version.** Rebase the branch on main; the controller reads live, main and every branch and gives `<R1>`.
- [ ] **Step 2: Guides.** Each screen and option R1 brings, written for Administrators and for schools, `Settings > Institutions` written as a path.
- [ ] **Step 3: Version and changelog.** In the readme's voice: areas and assignment, the assignment email, the booking link, the Tutor level and the tutor list.
- [ ] **Step 4: Every check** (Global Constraints). Expected: all pass, 0 standards errors.
- [ ] **Step 5: Commit** `git commit -m "<R1>: the guides, the readme and the version"`.

---

# Part 2: Release R2 (the record on the site, Talks, the copy, the import)

Built from main after R1 is merged and after the Institutions testing reports of 20 November 2026.

### Task 10: The record on the site: one setting, and no Airtable read behind it

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (`'institutions_on_site' => false` in `defaults()` and in `scopes()['advanced']`) and `includes/class-wpcpm-settings-screen.php` (`render_tab_advanced()`: one checkbox row)
- Modify: `includes/modules/class-wpcpm-institutions-index.php` (`on_site()`)
- Modify: `includes/modules/class-wpcpm-institutions-sync.php` (`tick()`'s `countries` and `records` phases)
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`phase_lookups()`: the `institutions` lookup)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`duplicate_search()` around line 3884) and `includes/modules/class-wpcpm-institution-approval.php` (`search()`, `index()`)
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`handle_accept()`'s live read around line 1445; `RECORD_DOCUMENT`)
- Test: `bin/test-institutions-sync.php`, `bin/test-mentors-sync-guard.php`, `bin/test-institutions-screen.php`, `bin/test-institution-approval.php`, `bin/test-institution-agreement.php`, `bin/test-settings.php`

**Interfaces:**
- Produces:
  - `WPCPM_Institutions_Index::on_site(): bool` the setting.
  - `WPCPM_Institution_Agreement::RECORD_DOCUMENT = 'agreement_document'` (the Drive link, in the record).
  - In site mode: the sync's `countries` phase moves straight to `records`, counting the stored map; `records` takes `rows` from `WPCPM_Institutions_Index::rows()` and `documents` from each record's `agreement_document`, sets `indexed`, and rebuilds as today; `provision` and `revoke` (with `retry_agreements()`) run as today. `phase_lookups()` builds `institutions` from the index's names. `duplicate_search()` and `WPCPM_Institution_Approval::search()` match the index (name trimmed and lowercase, or the contact address lowercase); an adopted record is the index row already held. `handle_accept()` reads the stage from the index row.
  - The Advanced tab's row: label "Institutions' record", checkbox "The site keeps the record, and Airtable gets a copy", description "On from the switch: the sync stops reading the Institutions and Countries tables and every change is copied to Airtable. Off, the sync reads Airtable again and rewrites the site's list."

- [ ] **Step 1: Write the failing checks:**
  - **Review Focus 2:** with the setting on and a client that records every request, a whole sync run, a queue duplicate search, an approval (new and adopted), an acceptance and a mentors sync's lookups make no read of the Institutions table or the Countries table (no `fetch_page`, `fetch_all` or `get_record` naming either); a new institution's approval makes one Airtable request, its `create_records()`, and an adopted one none.
  - The site-mode sync rebuilds every agreement option from the index row's agreement block and the record's link, provisions and revokes as today, and leaves the index and the countries map byte for byte as they were.
  - With the setting off, every one of those paths reads Airtable exactly as before (the existing suites unchanged).
  - `bin/test-settings.php`: `institutions_on_site` is false by default and in the `advanced` scope alone.

- [ ] **Step 2: Run** the task's suites. Expected: FAIL.

- [ ] **Step 3: Implement.** Ruling: one setting turns the record on and the copy on together, so there is no moment when the site writes without a copy or reads Airtable while it writes; the design's runbook steps "phase off" and "copy on" are one save. Ruling: in site mode the Countries table is not read either, and the stored country map (record ID and name, 196 rows) stays as last read; routing has not read it since R1.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-sync-guard.php` and `php bin/test-sync-schedule.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: institutions_on_site, the setting behind which the site reads nothing of Institutions or Countries"`.

### Task 11: The talks fields, the notes and the four scripts

**Files:**
- Create: `includes/modules/class-wpcpm-institution-notes.php`; `includes/modules/class-wpcpm-institution-talks.php` (the read side here; Task 15 adds the card) (requires in the loader and `uninstall.php`; `init()` from `WPCPM_Institutions::boot()`, `delete_all()` from `WPCPM_Institutions::uninstall()`)
- Modify: `includes/class-wpcpm-privacy-guard.php` (`private_post_types()` gains `wpcpm_inst_note`); `includes/modules/class-wpcpm-institution-record.php` (its docblock names the new writers)
- Test: `bin/test-institution-notes.php` (new), `bin/test-institution-talks.php` (new), `bin/test-privacy-guard.php`, `bin/test-uninstall.php`

**Interfaces:**
- Produces:
  - `WPCPM_Institution_Notes`: `POST_TYPE = 'wpcpm_inst_note'`, `META_FOR = '_wpcpm_inote_for'` (a record ID or a lead key), `META_AUTHOR = '_wpcpm_inote_author'`, `META_SOURCE = '_wpcpm_inote_source'`, `MAX_LENGTH = 5000`; `add( $key, $text, $author_id, array $extra = array() ): int|WP_Error` (`$extra`: `author_name`, `date` as `Y-m-d H:i:s` UTC, `source`); `for_key( $key ): array` newest first, each `array( 'id', 'text', 'date', 'author' )`; `has_source( $source ): bool`; `rekey( $from, $to ): int`; `register_post_type()`, `init()`, `delete_all(): int`. Private, not queryable, no REST, no UI; a note is never updated.
  - `WPCPM_Institution_Talks`: `RECORD_KEYS = array( 'estimated_students', 'first_students', 'next_step', 'follow_up', 'last_contact', 'help_desk', 'lead_owner' )`, `RECORD_APPLICATION = 'application_answers'`, `LOG_STAGE = 'talks_stage'`, `LOG_SAVED = 'talks_saved'` (the kinds Tasks 13 to 15 write); `facts( $key ): ?array` with `key`, `lead` (bool), `name`, `stage`, `country`, `country_name`, `city`, `website`, `contact_person`, `contact_email`, `assigned` (int), the seven record keys, and `application_answers`; `all_facts(): array<string, array>` every index row (one `WPCPM_Institution_Record::answers_for()` read) and, from Task 14, every lead; `stages(): string[]` `Lead`, then `WPCPM_Institution_Agreement::STAGE_ORDER`, then `TERMINAL_STAGES`; `closed(): string[]` the terminal three; `url( $key ): string` an institution's Institution Dashboard page (`ARG_VIEW`) at `#wpcpm-talks`.

- [ ] **Step 1: Write the failing checks:**
  - Notes: `add()` stores a private post with the text, the key, the author's ID or `author_name`, the given date and source; `for_key()` newest first; a text over 5000 characters or empty is refused; `rekey()` moves every note of a key and returns the count; `has_source()`; the privacy guard lists the type; uninstall deletes them.
  - The four scripts, through the record and a note, compared byte for byte after a read back: "Uniwersytet Źródlany", "Universitatea Babeș-Bolyai", "جامعة القاهرة", "東京大学" (Task 14 adds the same check for a lead).
  - `facts()` of an index row with a record holding `next_step` "Send the deck" and `follow_up` 2026-11-04 returns them with the row's name and stage; of an unknown key, null; `all_facts()` reads the records once (one `answers_for()` call counted).

- [ ] **Step 2: Run** `php bin/test-institution-notes.php`, `php bin/test-institution-talks.php`. Expected: FAIL.

- [ ] **Step 3: Implement** both. The storage is WordPress's own tables, written as every name the site holds today; the four-script proof against the host's latin1 connection is the Docker step in After the tasks ("R2 build and release", 3), which reads the stored bytes back with `wp db query` and expects single UTF-8 (`Ł` as `C581`, not `C385C281`).

- [ ] **Step 4: Run** the task's suites plus `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: the talks fields and notes, kept on the site"`.

### Task 12: The Airtable copy, written by the site, with its retry

**Files:**
- Create: `includes/modules/class-wpcpm-institution-copy.php` (require in the loader and `uninstall.php`; its hourly hook scheduled on `init`, cleared on deactivation and uninstall)
- Modify: `includes/modules/class-wpcpm-institution-details.php` (`write()`: in site mode the index first, then the record, then the copy)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`render_admin_page()`: the pending notice after `render_not_connected()`)
- Modify: `bin/test-fixtures.php` (every column of `COLUMNS` is in the fixture)
- Test: `bin/test-institution-copy.php` (new), `bin/test-institution-details.php`, `bin/test-institutions-screen.php`, `bin/test-fixtures.php`

**Interfaces:**
- Consumes: Task 10's `on_site()`; `WPCPM_Institutions_Sync::fields()`; `WPCPM_Airtable::update_records()`, `::create_records()`; `WPCPM_Lock`.
- Produces:
  - `COLUMNS = array( 'name' => 'Name', 'stage' => 'Current Stage', 'country' => 'Country', 'city' => 'City', 'website' => 'Website', 'contact_person' => 'Contact Person', 'contact_email' => 'Contact Email', 'confirmed_on' => 'Confirmed on', 'next_step' => 'Next Step', 'follow_up' => 'Follow-Up Date', 'last_contact' => 'Last Contact Date' )` (the name column follows `institutions_name_field`, as `Details::write()` does).
  - `OPT_PENDING = 'wpcpm_institution_copy_pending'` (record ID => `array( 'keys' => string[], 'since' => int, 'error' => string, 'tries' => int )`), `CRON_HOURLY = 'wpcpm_institution_copy_hourly'`, `LOCK = 'wpcpm_institution_copy_lock'`.
  - `cells_for( $record_id, array $keys ): array` column => value from the index row and the record now (`Country` as a one-ID link, an empty date as null).
  - `send( $record_id, array $keys ): bool` true when Airtable took it; otherwise the keys join the record's pending entry. Does nothing when not `on_site()`, for a lead key, or with no keys.
  - `create( array $row ): string|WP_Error` the new record ID, for a lead's promotion (Task 14).
  - `retry(): int` how many records were written; `pending(): array`; `render_pending_notice(): void`.

- [ ] **Step 1: Write the failing checks:**
  - `send( A, array( 'stage', 'next_step' ) )` PATCHes A with exactly `Current Stage` and `Next Step` from the site's values; outside site mode, for `lead-5`, or with no keys, nothing is sent.
  - **Review Focus 3:** a refused or timed-out PATCH leaves the site's change in place, stores A pending with both keys, the error and one try; a second failed send of `follow_up` merges the keys; `retry()` after the next save of `next_step` sends the newest value, clears A when Airtable takes it and keeps it (tries 2) when not; the Institutions screen's notice reads "These institutions' changes are not in Airtable yet. The site tries again every hour." with each school, since when and the error, and nothing once the list is empty.
  - `Details::write()` in site mode: the index patch and the record come first, then one `send()` of the changed columns; a patch that could not be made (lock) refuses with "The change could not be saved on the site, so nothing was changed. Try again in a while." and sends nothing; a failed copy still returns true.
  - `create()` POSTs Name, Current Stage, Country, City, Website, Contact Person and Contact Email and returns the record ID; a refusal returns the error.
  - `bin/test-fixtures.php`: every column of `COLUMNS` is a fixture field.

- [ ] **Step 2: Run** `php bin/test-institution-copy.php`, `php bin/test-institution-details.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** The retry derives the cells at retry time and never stores values, as the registrations' hourly retry derives its row; the pending option is read and written under `LOCK`.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-institution-details-card.php`, `php bin/test-uninstall.php`, `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: the site writes Airtable a copy of each institution, and retries what fails"`.

### Task 13: The agreement path on the site's record

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`handle_accept()` and the on-file route; every path that writes the agreement option also keeps `RECORD_DOCUMENT`)
- Modify: `includes/modules/class-wpcpm-institution-approval.php` (site mode: the record's `estimated_students` filled from the application's answer)
- Modify: `includes/modules/class-wpcpm-institution-history.php` (`words()`: `talks_stage`, "The stage of the talks with the institution was changed.")
- Test: `bin/test-institution-agreement.php`, `bin/test-institution-approval.php`, `bin/test-institution-history.php` (36 kinds)

**Interfaces:**
- Consumes: Task 10's `on_site()`; `WPCPM_Institutions_Index::patch()`; Task 11's `WPCPM_Institution_Talks::LOG_STAGE`.
- Produces: in site mode, a settling path patches the index row's agreement block after its Airtable write; acceptance and the on-file route, when they move the stage to Confirmed, patch `stage`, stamp `confirmed_on` with the day when it is empty, add `Confirmed on` to their one PATCH, and write a `talks_stage` row (`data` `from`, `to`); the Drive link is kept in the record whenever the option gets one.

- [ ] **Step 1: Write the failing checks:** in site mode, an acceptance of A at Agreement Sent leaves the index row at Confirmed with `confirmed_on` today and the agreement block Accepted, one PATCH carrying `Confirmed on`, and one `talks_stage` row (history sentence "The stage of the talks with the institution was changed."; 36 kinds); an acceptance at Revisit Later is refused as today; an on-file with a Drive link keeps the link in the record; a confirmed school's existing `confirmed_on` is not replaced; outside site mode nothing of this happens and the existing checks pass unchanged.

- [ ] **Step 2: Run** `php bin/test-institution-agreement.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** Ruling: the agreement path keeps its own Airtable writes and their pending marks (they are "the agreement columns it writes today", design 9), and an acceptance still refuses when Airtable refuses its PATCH, as today; rewiring seven agreement writes onto the copy is risk the switch does not need, and the cells that must never fail (generate, upload) already retry.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-agreement-generate.php` and `php bin/test-institution-panel.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: the agreement path writes the site's record and stamps Confirmed on"`.

### Task 14: Leads

**Files:**
- Create: `includes/modules/class-wpcpm-institution-leads.php` (require in the loader and `uninstall.php`; `init()` and `delete_all()` from `WPCPM_Institutions`)
- Modify: `includes/modules/class-wpcpm-institution-audit.php` (`META_LEAD`, `record_lead()`, `entries_for_lead()`, `move_lead_rows()`); `includes/class-wpcpm-privacy-guard.php` (`wpcpm_inst_lead`); `includes/modules/class-wpcpm-institution-talks.php` (`facts()` and `all_facts()` read leads)
- Test: `bin/test-institution-leads.php` (new), `bin/test-audit.php`, `bin/test-privacy-guard.php`, `bin/test-uninstall.php`

**Interfaces:**
- Consumes: Task 1's `administrator_for()`; Task 3's `notify()`; Task 11's notes; Task 12's `create()`; `WPCPM_Institutions_Index::insert()`; `WPCPM_Institution_Record::set()`.
- Produces:
  - `POST_TYPE = 'wpcpm_inst_lead'`, `META_FIELDS = '_wpcpm_lead_fields'`, `META_SOURCE = '_wpcpm_lead_source'`, `STAGE = 'Lead'`, `KEY_PREFIX = 'lead-'`.
  - Fields: `name` (required, at most 200), `city`, `country` (a Countries record ID or ''), `contact_person`, `contact_email`, `help_desk` (an https address or ''), `lead_owner` (imported text), `last_contact`, `assigned`, and the talks keys `estimated_students`, `first_students`, `next_step`, `follow_up`.
  - `key( $post_id ): string`, `is_key( $value ): bool` (`/^lead-[1-9][0-9]*$/`), `get( $key ): ?array`, `all(): array<string, array>`, `by_source( $source ): string`, `name_taken( $name ): string` (the key of a lead or an institution with that name, trimmed and case-insensitive, or '').
  - `add( array $fields, $actor, $notify = true ): string|WP_Error` assigned by `administrator_for()` unless `assigned` is given; the assignee is emailed unless `$notify` is false or the assignee is the actor.
  - `update( $key, array $changes, $actor ): bool|WP_Error`.
  - `promote( $key, $stage, $actor ): string|WP_Error` the new record ID: `create()` first; then the index row at `$stage`, the record (talks keys, `help_desk`, `lead_owner`, `assigned`), `WPCPM_Institution_Notes::rekey()`, `move_lead_rows()`, a `talks_stage` row (`from` Lead), the lead post deleted.
  - `WPCPM_Institution_Audit::META_LEAD = '_wpcpm_log_lead'`; `record_lead( array $entry ): int|WP_Error` (`lead` in place of `institution`); `entries_for_lead( $key, $limit = 50 ): array`; `move_lead_rows( $lead_key, $record_id ): int`.

- [ ] **Step 1: Write the failing checks:**
  - `add()` of a lead in Poland assigns 11 and emails 11 once; with the actor 11, or `$notify` false, no email; an empty name is refused ("Give the school's name."); a name a lead or an institution already has is refused ("A school of that name is already in the talks.").
  - Never counted, never shown, never offered, never written: after `add()` and `update()` the index option is unchanged, `offered_to_forms()` and `by_stage()` hold no lead, no Airtable request was made, and `WPCPM_Institution_Copy::send()` refuses the key.
  - `update()` writes `record_lead()` rows (`talks_saved`, `assignment_changed`) that no institution reader lists.
  - `promote( 'lead-5', 'First Contact Made', 7 )` returns the created record ID; the index holds the row at that stage; the notes, the history rows and the assignment are the new record's; one `talks_stage` row; the lead is gone. With `create()` refused, the lead is untouched and the error says "Airtable could not create the institution's record, so it is still a lead. Try again in a while."
  - `promote()` to Confirmed is refused.
  - `facts( 'lead-5' )` reads the lead, `lead` true, stage `Lead`; `all_facts()` holds the leads; a lead named with each of Task 11's four names reads back byte for byte.

- [ ] **Step 2: Run** `php bin/test-institution-leads.php`, `php bin/test-audit.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** Ruling: leads are a store of their own until promoted, because the key must be an Airtable record ID (design 3) and a lead is never written to Airtable (design 4); promotion takes its key from the Airtable create, and a promotion Airtable refuses leaves a lead a lead. Ruling: a lead holds one contact person and one contact address, the two columns an institution has; an Outreach Tracker cell naming several people is kept whole as the contact person.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-institutions-index.php`, `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: leads, kept on the site and never counted, shown, offered or copied"`.

### Task 15: The Talks card and its handlers

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-talks.php` (the card, the save, the note)
- Modify: `includes/modules/class-wpcpm-institution-policy.php` (`ACT_TALKS`)
- Modify: `includes/modules/class-wpcpm-institutions-sync.php` (`close_gate()`, `open_gate()`; `phase_revoke()` calls `close_gate()` for each school it closes)
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`render()`: the card for an Administrator in site mode, after the agreement panel and before the modules)
- Modify: `includes/modules/class-wpcpm-institution-assignment.php` and `class-wpcpm-institution-details-card.php` (the details card's section is drawn only while not `on_site()`; `page_url()` lands on `#wpcpm-talks` in site mode)
- Modify: `includes/modules/class-wpcpm-institution-history.php` (`words()`: `talks_saved`)
- Test: `bin/test-institution-talks.php`, `bin/test-institution-policy.php`, `bin/test-institutions-sync.php`, `bin/test-institutions-dashboard.php`, `bin/test-institution-details-card.php`, `bin/test-institution-history.php` (37 kinds)

**Interfaces:**
- Consumes: Tasks 3, 11, 12, 13, 14; `WPCPM_Institution_Details::write()` (an institution's country); `WPCPM_Dashboard_Card::open()`, `::close()`; `WPCPM_Flash::keep()`, `::kept()`.
- Produces:
  - `WPCPM_Institution_Policy::ACT_TALKS = 'talks'` with the grounds `array( GROUND_MANAGER )`.
  - `WPCPM_Institution_Talks`: `ANCHOR = 'wpcpm-talks'`, `ACTION_SAVE = 'wpcpm_talks_save'`, `ACTION_NOTE = 'wpcpm_talks_note'`, `FLASH = 'institution_talks'`, `REVISIT_DAYS = 90`; `render( $key, array $context = array() ): void`; `save( $key, array $posted, $actor ): array|WP_Error` (`array( 'changed' => string[], 'said' => string[] )`); `handle_save(): void`; `handle_note(): void`.
  - Posted: `key`, `stage`, `wpcpm_talks_assigned`, `country`, `estimated_students` (0 to 100000, '' clears), `first_students` and `follow_up` (`Y-m-d` or ''), `follow_up_drawn` (hidden, the value drawn), `next_step` (one line, at most 200), `contact_person`, `contact_email` and `help_desk` (a lead's only), `leave` (1 once confirmed); a note: `key`, `note`.
  - `WPCPM_Institutions_Sync::close_gate( $record_id, $actor = 0 ): array` (`locked` bool, `revoked` int): deletes the agreement option and, when `institution_on_inactive` is `revoke`, detaches each member through `WPCPM_Institution_Members::detach()` with reason `revoked`, so who is told is who the sync's revoke tells today. `open_gate( $record_id ): bool`: rebuilds the option from the index row's agreement block and the record's `agreement_document`.

- [ ] **Step 1: Write the failing checks:**
  - Who: a member never sees the card and a member's hand-made post to either action answers 403 with nothing written; outside site mode the card is not drawn.
  - The card for an Administrator: stage (a select of every stage but Confirmed; Confirmed shown as the current value only at Confirmed; Lead offered to a lead alone) with the line "Since <date>, by <name>." from the newest `talks_stage` row; the assigned Administrator as one combobox; contact person and address shown with "Change them on the details card." linking to it (editable fields for a lead); country (one combobox); estimated students; first students begin (a date); next step; follow up on (a date) with "Past due" when before today; last contact and help desk shown, not editable; one Save; the notes, newest first, each "<date>, <author>: <text>", and an "Add a note" form of its own with an "Add the note" button.
  - Confirmed: a posted Confirmed when the school is not at Confirmed is refused with "Confirmed is set when the Collaboration Agreement is accepted or recorded as on file." and nothing is saved.
  - **Review Focus 1:** a move from Waiting on Reply to Not Moving Forward without `leave` saves nothing and draws "Moving <name> to Not Moving Forward removes the access of its 2 accounts. Save again to confirm." (with no accounts, or `institution_on_inactive` keep: "Moving <name> to Not Moving Forward locks its Institution Dashboard. Save again to confirm."), the typed values kept; with `leave` 1 the stage is saved, `close_gate()` deletes the option and detaches both, and the copy sends `Current Stage`. Moving it back to Info Sent calls `open_gate()`, and the school's members' gated actions are allowed again on the rebuilt option with its Drive link.
  - Revisit Later: a move into it whose `follow_up` equals `follow_up_drawn` sets it to the day plus 90 and says "Follow up on is set to <date>, 90 days ahead. Change it if you like."; a date typed in the same save wins.
  - Rows: a save changing the stage and the next step writes one `talks_stage` (`from`, `to`) and one `talks_saved` (`data` `changed`, message naming the fields, as "Next step and follow up on were changed."), and a reassignment one `assignment_changed` with the email to the new assignee; an unchanged save writes nothing and says "Nothing was changed."; history sentence of `talks_saved` "The talks with the institution were updated."; 37 kinds.
  - The copy: an institution's save sends exactly its changed copy columns; its country goes through `Details::write()`.
  - A lead at Lead saved with First Contact Made is promoted and the redirect lands on the new school's page at `#wpcpm-talks`.
  - Notes: "Add the note" adds one note (author the poster, today) and no history row; an empty one says "Write the note first."; a second press of the same text within a minute adds nothing.
  - The details card's Assigned Administrator section is drawn while the setting is off and not while it is on.

- [ ] **Step 2: Run** the task's suites. Expected: FAIL.

- [ ] **Step 3: Implement.** The card is drawn in the dashboard card shell (`WPCPM_Dashboard_Card::open()` and `::close()`, key `talks`) and follows the Institution Dashboard's rules. The handler's order is the details card's: nonce keyed to the posted key, the key's shape (a record ID or a lead key), `ACT_TALKS`, the school's lock, `save()`. An institution's save writes the index (`patch()` for the stage), the record, `Details::write()` for the country and `WPCPM_Institution_Assignment::assign()`; a lead's goes through `WPCPM_Institution_Leads::update()`, or `promote()` for a stage. Ruling: the confirmation before leaving the active stages is asked by the server (the post comes back with the sentence and Save again), so it holds without script and the handler stays the fence. Ruling: Revisit Later's 90 days are proposed at the save that moves into it. Ruling: adding a note writes no history row, since the note is itself dated and signed. Ruling: an institution is never moved back to Lead, since it already has an Airtable record and may have accounts. Ruling: last contact is shown and not edited, since design 5 does not list it among the card's fields. Ruling: the gate closes and reopens at the save rather than at the next sync, because the site is now the record and nothing else will read the change.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-institution-members.php`, `php bin/test-institution-panel.php`, `php bin/test-submit-guard.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: the Talks card on each school's page, for Administrators"`.

### Task 16: The Talks view in the Administrator Dashboard frame

**Files:**
- Create: `includes/modules/class-wpcpm-institution-talks-view.php` (require in the loader and `uninstall.php`)
- Modify: `includes/modules/class-wpcpm-administrators-dashboard.php` (`VIEW_TALKS`; `view()` takes it while `on_site()`; `render()` draws it; `frame_args()` puts "Talks" after Watch)
- Modify: `includes/class-wpcpm-icons.php` (a `chat` icon: `<path d="M4.5 5.5h15v10h-8l-4.5 3.5v-3.5h-2.5z"/>`)
- Modify: `includes/modules/class-wpcpm-institution-leads.php` (`handle_add()`, hooked for `ACTION_ADD`)
- Test: `bin/test-institution-talks-view.php` (new), `bin/test-administrators-dashboard.php`

**Interfaces:**
- Consumes: Task 11's `all_facts()`, `stages()`, `closed()`, `url()`; Task 14's `add()`; Task 1's `has_area()`; `WPCPM_Dashboard_Card::group()`, `::open()`, `::close()`; `WPCPM_Dashboard_Frame::item()`.
- Produces:
  - `WPCPM_Administrators_Dashboard::VIEW_TALKS = 'talks'`.
  - `WPCPM_Institution_Talks_View`: `ARG_WHO = 'wpcpm_talks_who'` (`mine` or `all`), `ARG_FIND = 'wpcpm_talks_find'`, `ARG_LEAD = 'wpcpm_lead'`; `render( WP_User $viewer ): void`; `rows( array $facts, $who, $viewer_id, $find ): array`; `due_count( $viewer_id ): int` (the menu's badge).
  - `WPCPM_Institution_Talks::due( array $facts, $today ): array` every follow-up on or before `$today`, oldest first, leaving out Not Moving Forward and SPAM (Task 17 uses it too).
  - `WPCPM_Institution_Leads::ACTION_ADD = 'wpcpm_talks_add_lead'`; `handle_add(): void`.

- [ ] **Step 1: Write the failing checks**, with nine schools across five stages and two leads, three follow-ups due:
  - The menu: outside site mode no "Talks" item and `?wpcpm_view=talks` draws the Overview; in site mode "Talks" (icon `chat`) after Watch, current on the view, badge the viewer's due count, tone amber.
  - The head: "3 follow-ups are due" and "The oldest, <name>, has been due since <date>."; with none "No follow-up is due" and "Every next step is ahead of its date, or has none."; the counts follow Mine or All.
  - "Follow-ups due": school (linking to `url()`, a lead to the view with `ARG_LEAD`), next step, due date, oldest first.
  - By stage: one card per stage from Lead to Student holding schools, each titled with its count, open; "No stage" last when any school has none; Revisit Later, Not Moving Forward and SPAM folded; each row the name, city and country, the assignee's label, the next step and the follow-up date (marked past).
  - Mine and All: two links at the top right, Mine the default for a viewer with an area and All for one without; Mine holds the viewer's schools and the unassigned ones, marked "Not assigned".
  - Find: `wpcpm_talks_find=lodz` keeps the schools whose name holds it without regard to case or Polish letters (`remove_accents()`), and says "No school matches that name." when none.
  - Add a lead: a folded card with Name (required), City, Country (one combobox), Contact person, Contact email, Help desk link, Next step, Follow up on and Note, and "Add the lead"; a post makes the lead (first note added when typed) and lands on its page with "The lead is added."; the refusals are Task 14's sentences, the typed values kept; a member's post answers 403.
  - The lead page (`ARG_LEAD`): the frame, "Back to Talks", and the Talks card for that lead; an unknown lead says "That lead is not in the talks any more."

- [ ] **Step 2: Run** `php bin/test-institution-talks-view.php`, `php bin/test-administrators-dashboard.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** Ruling: Mine holds the unassigned schools as well, because the weekly email treats an unassigned follow-up as everyone's and the view must not hide what the email lists. Ruling: Student is drawn after Confirmed and open, since it is an active stage an Administrator moves on; only the three terminal stages fold. Ruling: a lead's Talks card opens on the Talks view, since a lead has no record ID and so no Institution Dashboard page.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-dashboard-card.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: the Talks view on the Administrator Dashboard, and Add a lead"`.

### Task 17: The Monday email of follow-ups due

**Files:**
- Create: `includes/modules/class-wpcpm-institution-talks-weekly.php` (require in the loader and `uninstall.php`; its hook cleared on deactivation and in `uninstall.php`)
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` and `includes/mail/templates/institutions.php`
- Test: `bin/test-institution-talks-weekly.php` (new), `bin/test-mail-templates.php`, `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: Task 16's `WPCPM_Institution_Talks::due()`; Task 11's `all_facts()`, `url()`; Task 1's `has_area()`; Task 3's `assignee_of()`; `WPCPM_Mail::send()`; `WPCPM_Lock`.
- Produces: `CRON_DAILY = 'wpcpm_institution_talks_daily'`, `OPT_WEEK = 'wpcpm_institution_talks_week'`, `LOCK = 'wpcpm_institution_talks_lock'`, `MAIL = 'institution-follow-ups-weekly'`; `next_run( $now ): int` the next 07:00 UTC; `recipients(): WP_User[]`; `run( $now = 0 ): int` how many emails went.

- [ ] **Step 1: Write the failing checks:**
  - `next_run()` of Monday 06:59 UTC is 07:00 that day, of 07:00 the next day's.
  - `run()` on a Monday (UTC) in site mode sends once per ISO week (`gmdate( 'o-W' )` noted before the first email); on any other day, outside site mode, or a second time in the week, nothing.
  - Recipients are the Administrators with an area or at least one assigned school; an account with neither gets nothing.
  - 11 with two due schools gets one email listing both, oldest first, each line "<name>: <next step>, due <date>: <link>" ("<name>, due <date>: <link>" with no next step); 12 with nothing due gets nothing while no unassigned school is due.
  - One unassigned school due: it is listed under "Not assigned to anybody:" in every recipient's email, 12's included, whose own list then says "None of the schools assigned to you is due."
  - A school at Not Moving Forward or SPAM is never listed; Revisit Later's is.
  - Template: catalog label "Institution follow-ups due this week, to the Administrator", audience Administrator; subject "[{site_name}] Follow-ups due this week: {count}"; body "These follow-ups are due, oldest first:" / "{follow_ups}" / "{unassigned_follow_ups}" / "Open the Talks view: {talks_link}"; eol "\r\n"; `follow_ups` required.

- [ ] **Step 2: Run** `php bin/test-institution-talks-weekly.php`. Expected: FAIL.

- [ ] **Step 3: Implement** in the shape of `WPCPM_Student_Flags::run_daily()` and `weekly()`: a daily event at 07:00 UTC, the lock, the week noted first, the lines made while each email is built in its reader's language. Ruling: an unassigned due follow-up counts as due for every recipient, so with one such school every recipient is emailed; the design lists them "in every Administrator's email" and an unassigned school must not go unseen. Ruling: the Monday is UTC's, since the time is. Ruling: the recipients are the Administrators the site knows by their work, those with an area or an assigned school, because the manage capability is granted to WordPress's Administrator role and so also reaches technical accounts (design 7: "never every WordPress administrator account").

- [ ] **Step 4: Run** the task's suites plus `php bin/test-mail-catalog.php`, `php bin/test-uninstall.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: the Monday email of follow-ups due, to each Administrator"`.

### Task 18: The import: wp wpcredits institutions_import

**Files:**
- Create: `includes/modules/class-wpcpm-institutions-move.php` (require in the loader and `uninstall.php`)
- Modify: `includes/class-wpcpm-airtable.php` (`fetch_comments()`); `includes/class-wpcpm-cli.php` (method `institutions_import`); `includes/modules/class-wpcpm-institution-history.php` (`words()`)
- Test: `bin/test-institutions-move.php` (new), `bin/test-airtable.php`, `bin/test-cli.php`, `bin/test-fixtures.php` (the Outreach Tracker map against its fixture), `bin/test-institution-history.php` (38 kinds)

**Interfaces:**
- Consumes: the "Before the tasks" fixtures; Tasks 3, 7, 11, 14; `WPCPM_Institutions_Sync::last_read()`.
- Produces:
  - `WPCPM_Airtable::fetch_comments( $table, $record_id ): array|WP_Error` every comment, paged by `offset`, each `array( 'id', 'text', 'created', 'author_name', 'author_email' )`.
  - `WPCPM_Institutions_Move`: `OUTREACH_TABLE = 'tblQssm68GvGEfAEH'`; `OUTREACH_COLUMNS` (key => the fixture's column name, for `name`, `city`, `country`, `contact_person`, `notes`, `last_contact`, `lead_owner`, `help_desk`, `promoted`); `INSTITUTION_COLUMNS` (record key => column for the first table's rows that come over into the record: the talks columns, FreeScout, the application answers and Agreement Document, with Notes, Comments and "Original program manager"); `LOG_IMPORTED = 'talks_imported'`; `read( WPCPM_Airtable $airtable ): array|WP_Error`; `read_file( $path ): array|WP_Error`; `plan( array $read ): array`; `apply( array $plan ): array`.
  - `wp wpcredits institutions_import [--dry-run] [--save=<file>] [--from=<file>]`: `--save` writes what was read as JSON (`-` for standard output, the summary then on standard error); `--from` reads it instead of Airtable.

- [ ] **Step 1: Write the failing checks**, from a read holding three institutions (one with Notes, a Comments cell, two record comments, a next step and a follow-up), a record the index does not hold, four Outreach rows (one owned by 11's address, one by a name nobody has in Japan, one promoted whose name matches institution A with different case and spacing, one promoted matching nothing) and one new tutor:
  - Refusals: with `institutions_on_site` on, "The site already keeps the record. The import runs before the switch."; with the last sync older than 30 minutes, "Run the institutions sync first: its last read is older than 30 minutes."
  - The institution with everything: its record gets the talks keys, `help_desk`, the application answers and `agreement_document` where empty; three notes plus the comments: Notes and Comments dated the import day with author "Imported from Airtable" and "Imported from Airtable (Comments column)", each record comment with its own date and author name; one `talks_imported` row ("Brought over from Airtable: 4 notes, the next step and the follow-up.", history sentence "The institution's talks were brought over from Airtable."); 38 kinds.
  - An unassigned institution is assigned by area, else by "Original program manager", with no email.
  - Leads: the owned row is a lead assigned to 11; the Japan row is unassigned and its owner kept as `lead_owner`; each gets its notes as a note "Imported from the Outreach Tracker", its last contact and help desk link, and its source; the promoted row adds its notes and help desk link to A, keeps its owner as A's `lead_owner`, and makes no lead; the unmatched promoted row is a lead, named in the summary.
  - The new tutor joins its school's list.
  - No school loses access by the switch: after `apply()` and then a site-mode sync run (Task 10), every school that was settled before is settled, its Drive link the one Airtable held.
  - **Review Focus 5:** `apply()` run twice, or run again after stopping after the second institution, adds no note, comment, lead or tutor twice (by `META_SOURCE`: `airtable-notes:<rec>`, `airtable-comments:<rec>`, `airtable-comment:<id>`, `outreach:<rec>`, `outreach-notes:<rec>`), never overwrites a value already on the site, and the mail capture stays empty.
  - `--dry-run` writes nothing and prints the summary: institutions read, the one not in the index, notes, comments, follow-ups, leads with how each was assigned, promoted leads matched (name and record) and not, tutors, and every unresolved owner or country.
  - `--save` then `--from` gives the same plan as the read itself.
  - `fetch_comments()` follows `offset` across pages and maps the four fields.

- [ ] **Step 2: Run** `php bin/test-institutions-move.php`, `php bin/test-airtable.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** The index and the agreement columns come from the last sync run, which the import requires to be fresh; the import reads only what the sync does not. Ruling: a promoted Outreach row is matched by name (trimmed, lowercase, runs of spaces as one), and one with no match comes over as a lead and is named, so an Administrator merges it by hand; the export of 6 November matched 20 the same way. Ruling: the areas and booking links are R1's seed and are not read again. Ruling: `--save` and `--from` exist for the rehearsal, since the Docker copy cannot reach Airtable; the saved file holds personal data and is kept only under `~/Projects/wpcredits-airtable-exit/`, never in a repository.

- [ ] **Step 4: Run** the task's suites plus `php bin/test-roles.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "<R2>: wp wpcredits institutions_import, the talks, notes, leads and tutors brought over"`.

### Task 19: R2's guides and version

**Files:**
- Modify: `docs/sections/31-admin-settings.md` (Advanced tab: the Institutions' record setting), `docs/sections/34-admin-operations.md` (the Talks view, the Talks card, leads and Add a lead, the stages and what leaving the active ones does, the weekly email, the copy and its notice, the import), `docs/sections/50-institution-dashboard.md` (nothing a school sees changes; say so only if the guide promised Airtable anywhere); then `php bin/build-docs.php` twice
- Modify: `wpcredits-program-manager.php`, `readme.txt` (`= <R2> =`), the pot

- [ ] **Step 1: Rebase and read the version** (the controller gives `<R2>`).
- [ ] **Step 2: Guides.** Each screen and option R2 brings; the stages in the Talks view's order; that Confirmed comes from the agreement.
- [ ] **Step 3: Version and changelog**, saying the record moves only when the setting is turned on.
- [ ] **Step 4: Every check** (Global Constraints). Expected: all pass, 0 standards errors.
- [ ] **Step 5: Commit** `git commit -m "<R2>: the guides, the readme and the version"`.

## After the tasks (the controller's and the product owner's, not a worker's)

**R1**

1. Final whole-branch review; fixes in one wave.
2. Docker trial (`~/Projects/wpcredits-docker`, localhost:8900, mail swallowed, Airtable refused by the safety plugin, faked where a read must be seen): the Areas section saved and refused; an application from an area and from none; a reassignment; a Tutor invitation accepted and that account's dashboard; the tutor list and the enrollment picker; the seed command's dry run against a faked read. Report and screenshots under the release folder.
3. `release.py check`, `build`, `scan`, `review` for `<R1>`.
4. On the product owner's yes: `mirror` and `deploy`; then on live, through the controller's ssh: `wp wpcredits institutions_seed --dry-run`, read with the product owner, then `wp wpcredits institutions_seed`; read back the Areas section and five institutions' assignees.
5. Note: the Airtable automation "New institution for one Administrator" is switched off on the product owner's yes at R1's deploy, so nobody gets the news twice. The imported tutors are not invited; inviting them is the product owner's separate yes.

**R2 build and release**

1. Build starts after the Institutions testing reports of 20 November 2026, from main with R1 merged.
2. Final whole-branch review; fixes in one wave.
3. Docker trial with invented data (Airtable faked by a stand-in that records every request): the import from a made-up read, the setting on, the Talks view and card, a lead added and promoted, the weekly email captured, the copy's PATCHes recorded; four leads named "Uniwersytet Źródlany", "Universitatea Babeș-Bolyai", "جامعة القاهرة" and "東京大学", each with a note, then `wp db query` reading `HEX()` of their stored names and notes: single UTF-8 (`Ł` as `C581`), never `C385C281`. Report and screenshots under the release folder.
4. `release.py check`, `build`, `scan`, `review` for `<R2>`; on the product owner's yes `mirror` and `deploy` with the setting off (R2 then behaves as R1 until the switch).

**The switch (first week of December 2026; every live step on the product owner's yes, and only that yes starts step 2)**

1. Rehearsal on the Docker copy with the real data: on the product owner's yes, `wp wpcredits institutions_import --dry-run --save=-` on live (a read of Airtable through the site, nothing written), piped over ssh into a file under `~/Projects/wpcredits-airtable-exit/`; on Docker, `--from=` that file, a dry run, then the import; the setting on; the Talks view and card read with the product owner; the copy written to the stand-in; the readers: list the Institutions columns the WPCredits Tracker, the Credits Monthly Report and `build_dashboard.py` read, and check each is written by the copy or the agreement path, naming to the product owner any column read and no longer written. The report is read with the product owner.
2. Freeze: the Institutions table and the Outreach Tracker are frozen, with a note to the Administrators the product owner sends (the controller drafts it).
3. The last read: the institutions sync run to completion from Institutions > Sync and storage, its report read.
4. The import: `wp wpcredits institutions_import --dry-run`, the summary read with the product owner, then `wp wpcredits institutions_import`.
5. The record on the site: Settings > Advanced, "The site keeps the record, and Airtable gets a copy", saved. This one save turns the sync's institutions phase off and the copy on.
6. The six Airtable automations off, each confirmed undeployed: Institutions 1 - Application Received (with the Airtable interest form, closed after #292), Revisit Later follow-up date, Institution confirmation date assignment, New institution for one Administrator (already off since R1), Student status in institutions table, Promote outreach lead to Institutions.
7. Read back on live: the index count is the import's; five schools checked against Airtable; one change of the test institution's next step on its Talks card reaches Airtable; the copy's notice is empty; the next Monday's email checked in the mail log.
8. The Administrators told where their talks are now (the product owner).

Going back: untick the setting; the sync reads Airtable again and rewrites the site's list. What Airtable never held is lost to it: the notes added after the switch, the first-students dates and the assignments, which stay on the site. When the students move, the copy stops and the table is archived (a later plan).
