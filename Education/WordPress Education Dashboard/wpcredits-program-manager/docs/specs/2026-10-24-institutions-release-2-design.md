# Institutions release 2, schools serve themselves: the design

Release 2 of the Institutions features ([2026-10-24-institutions-features-design.md](2026-10-24-institutions-features-design.md), section 4, approved on 24 October 2026), now designed in full. Design of 24 October 2026, for the WPCredits Program Manager plugin at 1.123.29 (live) and theme 1.32.2. The plugin version is the next free number when the build starts: 1.123.31 if Abilities and MCP, Phase A (1.123.30) ships first. Status: written, waiting for the product owner's approval.

## 1. Settled by the product owner

1. **The release covers** (C1, C2 and C7 of the review, approved 24 October 2026):
   - editing a student's details;
   - retiring the institution's notes card;
   - a card where a school corrects its own details;
   - a sample CSV for the student import;
   - the Institution guide, with the welcome post trimmed to a welcome.
2. **How corrections arrive today.** The program team corrects a school's details in Airtable when the school emails. The new card lets the school make the correction itself.
3. **What saves at once, and what waits.** An Administrator checks five details first: the name, the contact's email, the website, the city and the country. Everything else on the card saves at once: the contact person, their role, the signer and their title, the students' languages, and the institution's WordPress Credits page.
4. **The school's card reads first and edits on request** (mockup B, chosen 24 October 2026):
   - The details read as a list in two groups.
   - "Correct these" opens a form for its group only.
   - A change waiting for an Administrator shows as a line in the list, and the school can withdraw it.
5. **An Administrator decides a request as a whole** (mockup A, chosen 24 October 2026), in the Administrator Dashboard's "Requests from institutions" card:
   - each changed detail shows what is on record now and what the school asked for;
   - Approve writes every change to Airtable and the site at once;
   - Decline writes nothing and needs a note, which the school reads.
6. **Every change is told by email** to the address in "Who is emailed about institutions" (`agreement_notify`, the program's education address on live since 24 October 2026). That covers a saved change and a request alike.
7. **The Institution guide is a page on the site,** like the Student, Mentor and Administrator guides, kept current by each release. The dashboard's guide button points to it.
8. **The make.wordpress.org handbook's institutions page** gets a GitHub issue on WordPress/WPCredits holding the text to update, for the other Administrators to review. The issue is drafted for the product owner's yes before it is posted.
9. **A mentor is optional** (24 October 2026). "Ask for a mentor" does not come back, and a student with no mentor reads as normal.
10. **The roster stays as it is** (24 October 2026, when asked whether to rename it): students with no mentor are listed under the group "Waiting for a mentor", and the mentor cell says "No mentor yet". The student card uses the same words.
11. The standing rules apply:
    - no em or en dash, US English, "Administrators", full product names;
    - one type-to-find field for any long list;
    - a shared block looks as the Institution Dashboard draws it;
    - nothing shaped for Airtable: new data is a site field;
    - a version bump and a changelog entry;
    - the guides republished when they change;
    - every test send to the one test address;
    - roles, never people's names, in anything that is mirrored.

## 2. What the code says today (1.123.29)

- **Who may act for a school.** Every account of a school is equal: `WPCPM_Institution_Policy::grounds()` gives the manager ground (Administrators) and the member ground to every action but the semester report's editing. There is no lead or editor role among a school's members.
- **Where a school's details come from.**
  - The name, city, website, country (a link to the Countries table), contact person and contact email live in Airtable's Institutions table.
  - `WPCPM_Institutions_Sync` reads them every three hours into the option `wpcpm_institutions_index`, which it writes whole.
  - No path writes those five columns after an institution is created. The dashboard's identity header reads the index row.
  - The public WPCredits-Tracker reads the Institutions table's Name, so a name change reaches a public page.
- **The site-only answers.** Release 1's `WPCPM_Institution_Record` keeps the application's five site-only answers per institution (`get()`, `fill_empty()`).
- **The Sponsor Dashboard's precedent.** Its company profile card (`WPCPM_Sponsor_Profile`) saves at once and is audited, with no review. Its rows live on the site, since sponsors left Airtable.
- **Requests.** `WPCPM_Institution_Request` keeps a school's requests as private `wpcpm_inst_request` posts, with a kind, a state, a note and the actor. The Administrator Dashboard's "Requests from institutions" card (`render_decisions()`) resolves them. The import raises `KIND_ADD` today; `KIND_MENTOR` is no longer raised.
- **The removed Phase 4 cards.** All three still have their code and registered handlers, and nothing draws them:
  - **"Correct this student's details"** (`WPCPM_Institution_Student_Form`, action `wpcpm_save_student`) corrects the student's name, start and end dates (an end at most 365 days after the start) and field of study, through a live Airtable claim, and writes the Students and Reports tables.
  - **The institution's notes** (`WPCPM_Institution_Notes`) keeps notes as mentor-note posts with an institution audience. Live holds none: 0 of the 167 notes, counted on 24 October 2026.
  - **"Ask for a mentor"** is the third.
- **The student import** reads a CSV with the headers name, email, field_of_study and tutor (aliases accepted). Its only help is one sentence beside the file box, and no sample exists.
- **The guides.**
  - `bin/build-docs.php` builds the student, mentor, administrator and sponsor guides from `docs/sections/`.
  - The release script republishes pages 558, 559 and 560 from `[guides]` in its config.
  - Every dashboard's guide button points into the make.wordpress.org handbook (`WPCPM_Handbook_Assistant::guides()`); the Institution Dashboard's goes to `.../handbook/education/credits/institutions/`.
- **The welcome post.** Post 753, "Welcome to your Institution Dashboard", is an ordinary post that no code writes. It still promises notes and "Asking for a mentor", says "Four things" over three, and names the old product name.
- **History rows.** History rows still show some words of the time they were written: older agreement rows say "program manager", and member rows show stored codes such as "(provisioned)".

## 3. The release, in full

### 3.1 The school's details card (C7)

- **Where:** the "Representatives and agreement" module of the Institution Dashboard, first, above the people.
- **Who sees it:** every account that may act for the school (a new policy action, `ACT_EDIT_DETAILS`, with the manager and member grounds), and an Administrator viewing the school.
- **A school whose agreement is not settled** does not get the card. It sees what it sees today, the agreement panel, and sets its name on the agreement document there. `ACT_EDIT_DETAILS` is not added to the ungated actions.
- **An Administrator's correction** saves at once in both groups: they are the checker, so no request is raised and nobody approves their own change. It is written to Airtable and the index (group 2 and the contact person) or the record (the rest), audited, and emailed to `agreement_notify` like a group 1 save.
- **The layout** follows the chosen mockup B, kept beside the session backups as `school-card-chosen-B.html` (not mirrored):
  - each group is a list of label and value rows under its small heading;
  - "Correct these" sits under group 1, and "Ask to correct these" under group 2;
  - a waiting change is a line right under the row of the detail it changes, ending with "Withdraw it".
- **Heading:** "Your institution's details"; lead: "What the program has on record."
- **Group 1, "Contact and agreement", saved at once:**
  - **The details:** the contact person; their role; who signs the agreement and their title; the students' languages; the institution's page about WordPress Credits.
  - **The form:** "Correct these" opens it in place, with the same fields, labels, help and rules as the application's questions (release 1). The languages are the mentor form's list, as checkboxes.
  - **Saving:**
    - the contact person is written to Airtable's Contact Person and to the index row at once;
    - the other five go to the institution's record (`WPCPM_Institution_Record`, which gains a method that sets a value, beside `fill_empty()`);
    - one audit row records the change, with field names only;
    - one email goes to `agreement_notify` saying which details changed, from what to what.
- **Group 2, "Name and address", checked by an Administrator first:**
  - **The details:** the name, the website, the city, the country (the type-to-find country field the application uses) and the contact's email.
  - **The form:** "Ask to correct these" opens it in place.
  - **Sending** writes nothing to Airtable or the index. It raises a request of a new kind, `KIND_DETAILS`, holding each changed detail's value on record and the value asked for, and emails `agreement_notify` with a link to the request.
  - **While it waits:**
    - each changed detail shows "Waiting for an Administrator: <value>, asked on <day>.", with "Withdraw it";
    - the group offers no new form: one waiting request per school, withdrawn or decided before another.
- **Values:** values the school did not change are not part of the request, and a form that changes nothing sends nothing.
- **Refusals:** a refused post keeps what was typed, as the plugin's other forms do, and says what to correct.

### 3.2 The Administrator's decision

- **Where:** a `KIND_DETAILS` request appears in "Requests from institutions" on the Administrator Dashboard and counts in the Institutions screen's queue like the other kinds.
- **What it shows:**
  - the school's name, linked to its page;
  - who asked and when, and how long it has waited;
  - a table of each changed detail: on record now (read from the index at draw time, so a change made in Airtable since shows) and asked for.
- **Approve and update the records:**
  - writes every asked value to Airtable's Institutions table in one update (Name, Website, City, Country as the linked record, Contact Email), then the index row;
  - closes the request as approved;
  - writes an audit row, and tells the asking account by email.
  - If Airtable refuses, nothing is written on the site, and the request stays open with the refusal's sentence.
- **Decline:** needs a note (20 to 2000 characters, as the revoke's). It writes nothing to Airtable, closes the request as declined with the note, writes an audit row, and emails the asking account with the note.
- **Withdrawal:** "Withdraw it" closes the request in a new final state, `withdrawn`, beside open, done and declined. It is listed among the recently closed requests, written to the history and emailed to `agreement_notify`. Approve or Decline on a withdrawn request is refused with a sentence saying the school withdrew it.
- **Afterwards:** the card shows every account of the school the outcome of its latest closed request, until the school sends its next one: "Updated on <day>." for an approval, the Administrator's note for a decline, nothing for a withdrawal.
- **The layout** follows the chosen mockup A, kept beside the session backups as `administrator-decision-chosen-A.html` (not mirrored): the changed details as a table of Detail, On record now and Asked for; the note box; the primary button "Approve and update the records"; "Decline" beside it.

### 3.3 Editing a student's details (C1)

- **Where:** "Correct this student's details" (the existing `WPCPM_Institution_Student_Form`) is drawn inside the student card, folded, for accounts the policy allows `ACT_EDIT_STUDENT`.
- **What it does:** it saves at once, as it did in 1.75.0: the name, the start and end dates, and the field of study, through the live claim it already makes. A refused post keeps what was typed.
- **Mentors:** the card's Mentor row reads "No mentor yet" when a student has none, as the roster's mentor cell does, and the Mentor email row is left out then.

### 3.4 What is retired

- **The institution's notes:**
  - the `WPCPM_Institution_Notes` class, its handlers and its institution audience in the mentor notes' read gate;
  - uninstall still removes any such rows through the mentor notes' cleanup.
- **The school-side code of "Ask for a mentor":** the form and the request handler. The Administrator's resolving of requests stays for every kind, so a mentor request already on record can still be closed.

### 3.5 The sample CSV

- **The link:** "Download a sample list" beside the import's file box.
- **The file:** a download made by the plugin, holding the header row `name,email,field_of_study,tutor` and one example row with an address at example.test and a field of study from the nine the import accepts. It is read by the import's own parser in a test, so the sample always imports.
- **Why not a file in the repository:** the public repository's privacy guard refuses any email address in a tracked `.csv`, so the list is made on request, as the roster export is, and is never a file in the repository.

### 3.6 The Institution guide (C2)

- **Built:** a fifth guide built by `bin/build-docs.php` from new docs/sections (the numbers after the sponsors'), describing the Institution Dashboard as it is after this release. It promises no mentor for every student.
- **Published on the site:** at launch, a page is created for it (access level: the school's members and Administrators), its ID is added to the release script's `[guides]`, and the release republishes it like 558 to 560.
- **Linked:** the dashboard's "Institution guide" button points to that page.

### 3.7 The words around it, for the owner's yes at launch

- **The handbook issue:** a GitHub issue on WordPress/WPCredits with the replacement text for the handbook's institutions page, linking the site's guide, for the other Administrators to review.
- **The welcome post:** welcome post 753 trimmed to a welcome, with the guide linked and the notes, the mentor request and the old name gone. It is written with an exact revision kept, as the hub templates were.

### 3.8 History in today's words, and carried cleanup

- **History:** a history row reads in today's words whatever its date:
  - agreement rows say Administrators;
  - member rows give the way an account joined in words (for example "added from wp-admin" rather than "(manager)");
  - a closed request keeps its outcome when its note cannot be shown.
- **From 1.123.29's deferred list:**
  - a record found in another status is put back to private when written;
  - the record class uses `WPCPM_Airtable::is_record_id()`;
  - both web address answers are capped at their length on the server;
  - the kinds list in the history suite is checked in both directions;
  - the Pipeline's three DRY points: the shared option sort, a constant for SPAM, `ARG_INSTITUTION`;
  - the routing line's multi-country case is tested again.

### 3.9 Emails

Five new templates, editable in Tools > Emails, each sent through the plugin's mail:

| Template | Sent to | When |
|---|---|---|
| `institution-details-saved` | `agreement_notify` | a group 1 save |
| `institution-details-request` | `agreement_notify` | a group 2 request, with its link |
| `institution-details-approved` | the asking account | Approve |
| `institution-details-declined` | the asking account, with the note | Decline |
| `institution-details-withdrawn` | `agreement_notify` | a withdrawal |

Their words are set in the plan.

### 3.10 Tests and trial

- **Suites:** the policy, the dashboard and card, the request kinds and the Administrator Dashboard card, the Airtable write and its refusal, the student form in the card, the retired code's absence, the sample's import, the guide's build, the history words, and the emails' catalog.
- **The Docker trial:** a group 1 save, a group 2 request withdrawn and another declined, and the card at desktop and phone width with its spacing measured. Approve stops at the copy's refused Airtable write, so the suites carry its success.

## 4. Not in scope

- The Sponsor Dashboard in the Institution Dashboard's shape: release 3, with its own design.
- Anything that needs a human mentor.
- Tutors, semester report comments and returns, and the cross-cohort export.
- The Airtable exit for institutions.
- A lead or editor role among a school's members.

## 5. The author's calls (for the product owner to check)

- **Who may correct details:** every account of the school, as with everything else it does; no new role.
- **One request at a time:** one waiting request per school; it is withdrawn or decided before another is sent.
- **Emails:**
  - the asking account, not every member, gets the approval or the note;
  - every saved change and every request emails `agreement_notify`, which names the changes, so the address sees contact names.
- **The student card:** no Mentor email row when the student has no mentor.
- **Administrators:** an Administrator's correction saves at once in both groups.
- **Schools whose agreement is not settled** get no details card; they set their name on the agreement document.
- **Withdrawal and outcomes:** a withdrawal is a final state of its own, kept and emailed; the latest outcome stays on the card for every account until the next request.
- **Retired code:**
  - "Ask for a mentor"'s school-side code is removed rather than left undrawn;
  - the notes code is removed whole, since live holds no note.
- **The guide page's access:** the school's members and Administrators.
- **The carried cleanup:** the deferred list was chosen from 1.123.29's final review triage.
