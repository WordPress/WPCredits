# Institutions features: the design

The features the Institutions module review of 22 October 2026 found missing, as the product owner decided them on 24 October 2026, in three releases of the WPCredits Program Manager plugin. Design of 24 October 2026, for plugin main at 1.123.28 and theme 1.32.2. Release 1 is designed in full here; releases 2 and 3 are outlined and each gets its own detailed design before it is planned. Status: written, waiting for the product owner's approval.

## 1. Settled by the product owner

1. **The review's ten feature questions are answered** (24 October 2026, "Yes to your recommendations", with these changes):
   - C1: "edit a student's details" comes back inside the student card on the Institution Dashboard; the institution's notes card is retired, with the welcome post's promise of it. "Ask for a mentor" does not come back (item 2).
   - C2: a short Institution guide like the other four guides; welcome post 753 is trimmed to a welcome.
   - C3: an institution's history (its audit entries) shown on its page, for Administrators only.
   - C4: a Revoke button for an institution's agreement, with a required note.
   - C5: older agreements stay as they are. The card says the program holds the copy, and a school asks its contact for it. Nothing is built.
   - C6: the Institution Dashboard's shape for both dashboards: the Sponsor Dashboard follows the Institution Dashboard.
   - C7: first a card where an institution corrects its own details, and a sample CSV for the student list import. Tutors, commenting on or returning a semester report, and an export across cohorts come later.
   - C8: "Mark as dropped out" stays the only control on a student Pending graduation for now. It becomes a "Tell the program" request (a request to Administrators, like the mentor requests) when the site takes over graduation from Airtable.
   - C9: on the wp-admin Institutions screen, Pipeline names are linked, there is one type-to-find search across the records, and counts leave out SPAM, Not Moving Forward, Revisit Later and No stage.
   - C10: the application keeps every question it asks today ("they will be used in the future") and adds the missing ones: who signs the agreement and their title, the contact's role, the language, and the institution's WordPress Credits page the agreement requires.
   - The language is the language the students study in, from the same list as the mentor form (24 October 2026, when asked whether it meant the agreement's language or the students').
2. **A mentor is optional** (24 October 2026). The program may reduce or change how mentors work, with students working on their own or with an AI mentor; the decision has not been made. Nothing built here needs a human mentor, and a student with no mentor reads as normal, not as a gap. The mentor features already live stay as they are.
3. **The Airtable interest form closes only when the Institutions module and its form go live** (24 October 2026). The site's form at /institution-application/ is open meanwhile, and the README and the pitch decks keep linking it.
4. **Nothing built here is shaped for Airtable.** New data is a site field, never an Airtable column; where Airtable must still agree, the site writes it (the rule of the sponsors' move, 23 September 2026).
5. The standing rules apply: no em or en dash anywhere, US English, "Administrators", full product names, one type-to-find field for any long list (never a search box above a select), a block a second dashboard shares looks exactly as the Institution Dashboard draws it, a version bump and a changelog entry per release, the guide pages republished when they change, every test send to the one test address.

## 2. What the code says today (1.123.28)

### 2.1 The application

- The form (`WPCPM_Institution_Application`, page /institution-application/, setting `applications_enabled`, on live) draws 13 fields in five groups (About your institution, Who we should contact, How your program works, Tell us more, Before you send this). Every field is keyed by its Airtable column, and posts as `wpcpm_application[f<12 hex of md5(column)>]`.
- A submit stores a private `wpcpm_inst_app` post. Its answers sit in `_wpcpm_app_fields`, keyed by column (11 of them; Country, stage and consent are held apart). Nothing reaches Airtable at submit.
- Approval (`WPCPM_Institution_Approval::approve()`) sends every key of `_wpcpm_app_fields` to the Institutions table under that exact name, without typecast. An unknown column makes Airtable refuse the record (422).
- The Institutions table has no column for a signer, a title, a role, a language or a page (`bin/fixtures/institutions-table-fields.json`), and `bin/test-institution-application.php` pins that every form column exists there.
- The queue's answers table (`render_application_answers()`) and the Administrator Dashboard's "Read the application" loop over `fields()`, so a field added there shows in both.
- An approved application is purged after the setting `application_approved_days`. Its answers then live only in Airtable, and only for the columns the sync never reads back.
- The Airtable form "WordPress Credits - Educational Institution Interest Form" (read 24 October 2026) asks the same twelve questions and the privacy box. The site's form already asks all of them.
- The agreement (`includes/templates/collaboration-agreement-en.php`, the only language file) fills in only `[Institution Name]`. Its signature block has blank Name, Title and Date lines, and section 2 says the institution will "Publish a dedicated page about the WP Credits program on the institution's website." The panel's language select is drawn only when a second template file exists.

### 2.2 The Pipeline tab

- `WPCPM_Institutions::render_pipeline()` groups the rows of the option `wpcpm_institutions_index` (the Airtable sync's copy, keyed by record ID) by stage, terminal stages last.
- A name prints as plain text with its record ID. The card total and every count on the tab run over all rows: the per-stage headings, Confirmed with no agreement, no live member, routing gaps and consent.
- There is no search.
- Two long plain selects run over the same rows:
  - the semester draft's institution picker (`WPCPM_Semester_Report_Screen::render_draft_picker()`, active stages with a roster);
  - the Reconciliation Link picker (`render_link_form()`, every record, SPAM included), once per unlinked student.
- `WPCPM_Dashboards::render_combo()` is the accessible type-to-find field. The Emails tool already uses it in wp-admin, with its rules in `emails.css`.
- An institution is addressed by its record ID. An Administrator opens its Institution Dashboard with `?wpcpm_institution_view=<record ID>`, and the Agreements tab already links names that way.

### 2.3 Agreements

- `WPCPM_Institution_Agreement::handle_revoke()` (action `wpcpm_agreement_revoke`, capability, nonce, policy) exists, but no button posts to it. It:
  - takes an agreement in state `accepted` and a note of 20 to 2000 characters;
  - writes Airtable's Agreement Status "Revoked" first;
  - then stores the state and the note, deletes the access gate option, cancels pending invitations and writes the audit row `agreement_revoke` (without the note);
  - mails every live member the note verbatim (`agreement-revoked`, or `agreement-revoked-no-contact`).
- Members keep their accounts and reach only the agreement panel. Reinstate exists on the Administrator Dashboard's Agreements card.
- On live, all 44 settled agreements are documents in state `accepted` (43 recorded on file, 1 accepted on the site), so the one handler serves every one of them.
- The sponsor side has the precedent: "Take it out of force", a form with a required note on the wp-admin Sponsors screen's Agreements card (`WPCPM_Sponsors::render_agreement_state()`). A typed note kept when a post is refused is in `handle_return()`.
- The institution panel's accept dialog (`render_accept_form()`), drawn on the institution's page and on the Administrator Dashboard, says "You can revoke it from here later."

### 2.4 The audit

- `WPCPM_Institution_Audit` writes private `wpcpm_audit_entry` posts. It records about 30 kinds for institutions: members, approval, agreement steps, the fence, imports, invitations, notes, requests and student changes.
- `entries_for( $record, $limit )` returns them newest first and is never called. Nothing renders an institution's entries, and nothing prunes them.

## 3. Release 1 (plugin 1.123.29): the complete application and the Administrators' tools

### 3.1 The new questions on the application (C10)

Every question asked today stays, worded as today. The new ones:

| Group | Label | Kind | Required |
|---|---|---|---|
| Who we should contact | Their role at the institution | text, up to 120 | yes |
| The agreement (new group, after How your program works) | Who will sign the agreement for the institution? | text, up to 120 | no |
| The agreement | Their title | text, up to 120 | when a name is given |
| The agreement | Your page about WordPress Credits | web address, up to 200 | no |
| How your program works | In which languages do your students study? | checkboxes, the mentor form's list | yes, at least one |

Help lines:
- **Role:** "For example: internship coordinator, or head of the computer science department."
- **Signer:** "Somebody who can commit the institution, such as a dean. Leave it empty if you do not know yet."
- **Title:** "For example: Dean of the Faculty of Engineering."
- **Page:** "The agreement asks the institution to publish a page about the program on its website. If it is up already, give its address; if not, leave this empty."
- **Languages:** "Tick every language your students study in."

The page answer is cleaned as the Website answer is.

The languages are the mentor form's own list, read from `WPCPM_Mentor_Application_Form::languages()`, so the two forms never offer different names. They are drawn as the mentor form draws them: a group of checkboxes, and an unknown value is dropped.

How the new answers are kept:
- **On the site only.** They are stored on the application post apart from `_wpcpm_app_fields`, and are never sent to Airtable at approval.
- **Tests.** The check that every form column exists in the Institutions table covers only the answers that go there. A new test pins that the approval's create cells are exactly today's.
- **Review.** The queue's answers table and the Administrator Dashboard's "Read the application" show them in form order, with the same loop. An answer without a column prints no column name.
- **Emails.** Unchanged: the acknowledgement and the Administrators' notice carry no answers.

### 3.2 The institution's record on the site

- On approval, the site-only answers are copied to a record of the institution on the site, keyed by its record ID. They outlive the application's purge.
- When approval joins the application to a record the program already has, the answers fill only the record's empty fields: the first answers stand, as the join writes nothing over the Airtable record. A second press of Approve changes nothing.
- The record is one private post per institution, kept the way the sponsors' rows are kept (`wpcpm_sponsor_co`).
- It is created on first need, is never autoloaded, is covered by the privacy guard and is deleted at uninstall.
- An institution approved before this release has no such answers, and its record is simply absent.
- This record is where release 2's corrections and the later Airtable exit add what Airtable holds today. Release 1 stores only the new answers in it.

### 3.3 The Pipeline tab (C9)

1. **Names are links** to the institution's page: its Institution Dashboard as an Administrator sees it, `?wpcpm_institution_view=<record ID>`, as the Agreements tab links them. A name prints plain when that page is not published.
2. **One field above the groups:** "Find an institution", built with `render_combo()`.
   - It runs over every record, whatever its stage. Each option reads "Name, City (Stage)".
   - It has an "Open its page" button beside it that goes to the same address.
   - Without JavaScript, the plain sorted select and the button still work.
3. **The counts leave out** SPAM, Not Moving Forward, Revisit Later and No stage.
   - This applies to the card total and to every count on the tab: Confirmed with no agreement, no live member, routing gaps and consent.
   - The four groups still list below the others, folded, each with its own count, under the line "Not counted above".
   - The list of four is fixed, apart from the setting "Institution pipeline stages", which other screens read and an Administrator may change.
4. **The two long selects become the same field:** the semester draft's institution picker and the Reconciliation Link picker. The Reconciliation list leaves out SPAM records.
5. **The field's rules in wp-admin** are the Emails tool's, loaded where both screens get them, without redefining a class another stylesheet owns.

### 3.4 The institution's history (C3)

- **Where.** On the institution's page, for Administrators only (`WPCPM_Roles::CAP_MANAGE`), a section at the foot, above the read line, titled "For Administrators". It holds two folded cards, shaped like the dashboard's other cards:
  - "From the application": the record of 3.2, read only, or "Nothing kept from the application" when there is none.
  - "History": the latest 100 entries of `entries_for()`, newest first.
- **What a history row shows:**
  - The date and time in the site's formats.
  - Who: the account's display name, "The site" for the system, or "A removed account".
  - What happened, as a plain sentence.
- **Plain sentences.** A kind whose stored message is not one gets its words from one map, as `summary_label()` does for agreement states. No stored kind, record ID or field name ever shows.
- **Limit.** When there are more entries, a line says "Showing the latest 100."
- **The revoke row** shows its note (3.5).
- **Members never** get the section, its markup, or the entries in any export or REST answer.

### 3.5 Revoke an agreement (C4)

- **Where.** On the wp-admin Institutions screen's Agreements tab, on each settled agreement (accepted, or recorded on file), as the Sponsors screen's Agreements card offers "Take it out of force". wp-admin is for Administrators only already. A refused post returns to the same tab and row.
- **The accept dialog's line** becomes "You can revoke it later on the Institutions screen, in the Agreements tab." It is true wherever the dialog is drawn.
- **The form.** A folded "Revoke this agreement" with:
  - a required note of 20 to 2000 characters;
  - the line "Every member of this institution receives this note by email.";
  - a confirmation question;
  - a button that submits once.
- **It posts to the existing handler.** Only two things are added to the handler:
  - the typed note is kept and offered back when a post is refused, as `handle_return()` does;
  - the note goes into the audit row's data, so the history can show it.
- **Everything else stays as today:** Airtable first, the gate, the cancelled invitations, the members' email, the stage untouched, and Reinstate on the Administrator Dashboard.

### 3.6 Guides, docs and the changelog

- The Administrator guide's sources (docs/sections 30 to 35) describe:
  - the Pipeline field and counts;
  - the For Administrators section;
  - Revoke.
- Page 560 is republished by the release.
- docs/sections/30-admin-wpadmin.md's Pipeline paragraph is rewritten.
- No email is added or changed.

### 3.7 Tests

New and changed cases, each written to fail before its fix:
- **The application suite:** the five new fields and their requiredness; signer without a title refused; the page cleaned; the site-only answers stored apart and absent from Airtable's cells; the fixture check limited to Airtable answers; and every existing pin updated, field count and fieldsets included.
- **The approval suite:** the institution's record written with exactly the site-only answers; the Airtable create cells unchanged.
- **The screen suite:** names linked (and plain without a page), the combobox and its button, the counts with the four stages out and their folded groups.
- **The semester report and unlinked-link suites:** the combobox, and SPAM out of the Reconciliation list.
- **The dashboard suite:** the For Administrators section drawn only for `CAP_MANAGE`, never for a member; no kind, record ID or field name in a row; the 100 limit and its line.
- **The agreement, screen and panel suites:** the Revoke form on every settled row of the Agreements tab and on no other; the note kept on a refused post; the note in the audit data; the accept dialog's new line.
- **The uninstall and privacy guard suites:** the institution record.

### 3.8 Trial and launch

- **On the Docker copy:**
  - a test application with the new answers, through the queue's answers table;
  - the Pipeline tab's links, field and counts, against a snapshot of the live index;
  - the For Administrators section as an Administrator and as a member;
  - the Revoke form on the Agreements tab, and its refused path.
- The copy refuses outbound Airtable writes, so approval and a revoke that goes through are trialled in the suites, not on the copy.
- There is no switch: the new questions show the moment the release is live.
- What the product owner does afterwards:
  - the handbook's Get Involved link;
  - closing the Airtable interest form, when they choose.

## 4. Release 2 (outline): schools serve themselves

- **Edit a student's details (C1).**
  - The existing "Correct this student's details" form (`WPCPM_Institution_Student_Form`, still registered) is drawn inside the student card, folded, for members the policy allows `ACT_EDIT_STUDENT`.
  - It corrects the student's name, start and end dates and field of study, and writes Airtable's Students and Reports rows as it did in 1.75.0.
  - The student card's mentor rows read plainly when a student has none.
- **Retire the notes card.** The institution's notes handlers and render go. Live holds no institution note (counted on 24 October 2026: 0 of the 167 notes of the shared notes type), so retiring the card removes nothing anybody wrote. "Ask for a mentor" stays undrawn; the Administrators' request card stays for the import's requests.
- **The institution's details (C7).**
  - A card like the Sponsor Dashboard's company profile, for the institution's members: name, city, website, country, contact person and email, the role, the signer and title, and the WordPress Credits page.
  - The five Airtable details are written to the Institutions table and the index at once, or the three-hourly sync would undo them. The rest go to the record of 3.2.
  - Saved at once and audited, like the sponsor profile.
  - Whether a change of name waits for an Administrator is a question for that release's design.
- **A sample CSV** linked beside the import's file box: a header row of name, email, field_of_study, tutor and one example row.
- **The Institution guide (C2).**
  - A fifth guide built by `bin/build-docs.php` from new docs/sections, without promising a mentor to every student.
  - Where it is published (a page of the site, or the handbook page the dashboard links today) is a question for that release's design.
  - Welcome post 753 is trimmed to a welcome by hand: no code writes it. Its new text is drafted for the product owner.

## 5. Release 3 (outline): the Sponsor Dashboard in the Institution Dashboard's shape (C6)

- **The two shapes today:**
  - The Institution Dashboard draws the identity header, then the help block (updates, resources, the program contact), then the agreement panel, then movable modules with a lead and arrows, then the read line.
  - The Sponsor Dashboard draws the identity header, an At a glance strip, four groups of cards, and resources at the foot.
- **The change:** the Sponsor Dashboard takes the institution's order and its module mover, its four groups becoming modules, and copies every shared block's rules value for value.
- **For that release's design:**
  - what becomes of the At a glance strip, which the Institution Dashboard does not have;
  - mockups for the product owner before any code.

## 6. Not in scope

- The Airtable exit for institutions: the site as the master of institution records, institutions made on the site, merging duplicates, country assignments, moving the stage, and the outside readers. The record of 3.2 is shaped to grow into it.
- C5 (nothing to build); C8's request (with the graduation move); C7's later items (tutors, semester report comments and returns, the cross-cohort export).
- "Ask for a mentor", and anything else that needs a human mentor.
- The review's data questions (the stage "Student", the two records that look like one school, country contacts without a name).
- A retention rule for audit entries, which keep invitees' addresses with no end date. That is worth its own decision before the history grows.

## 7. Open items (the product owner's)

None for release 1 so far.

## 8. The author's calls (for the product owner to check)

- The signer and the page are optional, because many applicants do not know them yet. The title is required once a signer is named.
- The new group "The agreement" sits between "How your program works" and "Tell us more".
- The languages question is required, with at least one ticked, and sits in "How your program works".
- The new answers are shown only to Administrators in release 1. Members see and correct them in release 2.
- The site keeps only the new answers. Today's answers keep going to Airtable as now.
- The Pipeline field opens the institution's page rather than filtering the tab.
- The four stages are left out by a fixed list, not by the active stages setting. The four groups stay on the tab, folded.
- The Reconciliation list leaves out SPAM records.
- History shows the latest 100 entries, and the revoke's note.
- Revoke lives on the Institutions screen's Agreements tab, where the sponsors' "Take it out of force" lives, and nowhere else.
- A second application joined to a known record fills only empty answers. The first answers stand.
- The counts that leave out the four stages are the Pipeline tab's. The Sync and storage tab, which shares one of the counts, keeps counting every record.
