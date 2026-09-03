# Institutions module

**Date:** 2 September 2026
**Component:** `wpcredits-program-manager` 1.65.0 (companion theme `wpcredits-theme`)
**Status:** approved design, not yet implemented
**Supersedes:** the Institutions module spec of 2 September 2026 (spec v1) and the four extension notes of the same date (Collaboration Agreement, student import and cohorts, semester report, members and policy), together with the review of all five. Those documents disagreed about the fence, the cohort calculation, where the roster lives, how a PDF is made and what a program manager may do at an unsettled institution. This document is the one that gets built. Where it changes something an earlier document settled, it says so in the sentence that changes it.

Today the module registers a `wpcpm_institution` role and nothing else: `includes/modules/class-wpcpm-institutions.php` is 55 lines, `is_implemented()` returns false, and the admin page falls through to `render_placeholder()`.

This document builds it out along the life of one institution: it applies on wordpresseducation.org, a program manager reviews and approves it, it gets an account that can do exactly one thing, it settles its Collaboration Agreement, its account opens, it imports its students, tracks them by semester, edits and graduates them, writes a semester report, and one day leaves. Airtable stays the system of record for the program. WordPress becomes the system of record for three things and only three: consent, membership, and the signed agreement document. Each of those exceptions is argued where it is made.

House rules that apply to every line below: comments explain why and name the bug or decision behind the rule; no em dashes; full product names ("Student Report Card", "Mentor Report Card"); every behaviour worth trusting has an assertion in `bin/test-*.php`.

---

## 1. Settled by the product owner

None of these is open. The design works inside them.

**From the first spec**

1. The public application form moves off Airtable and onto wordpresseducation.org.
2. A submission is held on the site for a program administrator to review, chiefly as a spam check, and is not written to Airtable until approved.
3. On approval the Airtable institution record and the site account are created together.
4. Institution users write directly to Airtable, fenced to their own records and an allowlisted set of fields, every change logged with who, when and old to new. The fence is server-side in every handler, never a hidden button.
5. In-app mentor acceptance (moving outreach off Slack) and replacing Sensei grading are out of scope.

**Decided after the first spec**

6. **The Collaboration Agreement is a hard gate.** An approved institution can log in and do one thing until its agreement is settled: generate the program's template or upload its own, sign offline, upload the signed PDF. A program manager accepts it. Acceptance unlocks everything else and moves the record toward `Confirmed`. Enforced server-side in every handler. Acceptance is a queue, so the review screen shows the age of every waiting item and managers are notified when one lands.
7. **The template** is a Google Doc the WordPress Foundation owns. Its address is not recorded here: the Doc is editable by anyone holding its link, so a link in a public repository would hand out write access to the wording institutions sign. A program manager sets it on the site, in the `agreement_doc_url` setting. Its only variable is `[Institution Name]`; it states it is not legally binding; it carries signature blocks for the WordPress Foundation and the institution. The plugin holds its own copy of the text, because a Doc cannot be rendered reliably and an outage must not block onboarding; this document says who keeps the two in step. The generated agreement is a PDF. Institutions needing different wording use the bring-your-own branch, which a program manager reads in full; two such agreements already exist (Politechnika Krakowska, UIN Banten).
8. **The signed-agreement upload is the most exposed surface in the module**: restricted types and size, stored where it can never be executed or guessed at by URL, served to reviewers as a download and never inline.
9. **Institutions import their own students**, individually or as a CSV batch, into any of the three programs, and select the batch's start date, which is required. Duplicate detection against email and WordPress.org profile runs before anything is created.
10. **Cohorts are derived, not stored.** A cohort is the semester (half-year) containing the start date. Rows with no start date get their own visible bucket so institutions correct them.
11. **Cohort comparison is participation counts only**: signed up and graduated, this semester against the previous one. The same two numbers the semester report carries, from one calculation.
12. **The semester report is a generated draft the institution edits.** The plugin fills counts, teams, blog and reflection links and candidate quotes; the institution writes the narrative. Output is a page with a PDF export, because the reference report is full of links. Naming or quoting a student needs that student's consent.
13. **Several users per institution, all with equal power**, many-to-one from day one. Membership is the permission. Any member can invite another; removal is as easy as inviting; a program manager can always remove a member. The invite flow may ship later; the model must not need migrating.
14. **Joint projects across institutions are designed for, not built**: the fence is a policy function with one clause today and room for a field-scoped second; a joint project needs a project entity that does not exist.
15. **One shared review queue** any program administrator can action; the Countries table's `Person of contact (Team)` is shown for information only.
16. **Rejected applications get a neutral acknowledgement with no reason.** Spam is closed silently.
17. **Airtable schema changes** are made by the developer through the API, each announced first. Every new field is listed in section 10.
18. **A clearly labelled TEST institution record** is what the module is built against. No real partner's data is touched before the pilot.

**Answers to the first spec's open questions**

19. **Which link is authoritative.** The Student Report Card and the Mentor Report Card keep keying on Students Reports (`tbljYkkVGbeoaWEtY`). For everything on the institution side (roster, cohorts, import, semester report, fence) the authority is the **Students table** (`tbla8GZg5x6NY7aWt`), reached through `Institutions.Students` (`fldjoWYVLy9IMj6LU`). This reverses the first spec's section 4. A student who exists in one table and not the other is handled explicitly, never dropped.
20. **Institutions may mark a student graduated or withdrawn**, accepting that an Airtable automation mails the student and cannot be recalled. The confirmation names the email; the change is logged.
21. **`Paused` and `Pending graduation` become tracked statuses**, changing both syncs, every mentor's list, and provisioning accounts for the nine students in those states.
22. **Approval sets `Current Stage`** (`First Contact Made`), accepting that `New institution for Pooja` (`wflf99mKIfbfq6gMH`), which fires on an empty stage, will not fire for records created here. That is a recorded behaviour change, not a broken automation.
23. **The Developer Track has no hours target.** `hours_target()` returning 0 for it is the answer, not a placeholder; the roster prints no target, never "0 of 0".
24. **The 38 Confirmed institutions without recorded consent all hold a signed Collaboration Agreement** in a Drive folder the Foundation owns, which is not shared by link and is not identified here. That is the legal basis for provisioning and emailing them. They are never forced back through the gate: a program manager records an "agreement on file" state with a Drive link, and the bulk provisioning proceeds once every Confirmed institution has one. **Recorded in bulk since Phase 2 (1.72.0):** the on-file route has a second form on the Institutions screen that applies to every Confirmed institution with nothing recorded, with the one Drive link they share, because every institution at Confirmed signed before this site could record it and thirty-eight hand entries is the kind of chore that gets done for the pilot and nobody else. Same write per institution, same lock, same audit row, same Airtable cells.

---

## 2. Settled by this document, because five documents disagreed

Nobody could start work while four fence APIs, two cohort classes, three roster stores and two PDF strategies all claimed to be the plan. These are decided here, and each later section is written against the decision.

| Question | Decision | Why the alternative was rejected |
| --- | --- | --- |
| The fence | One class, `WPCPM_Institution_Policy`, one function `decide( $action, $subject, $user )` returning a scoped decision array, `ACT_*` constants, one `ungated()` list, one refusal. `allows()`, `permit()` and `grant()` from the extension notes are deleted | Under `decide()` as specified, `allows( 'agreement:upload' )` was an unknown action and failed closed, so nobody could ever upload. `grant()` reinstated an inline institution-equals-institution comparison and left the gate to seven handlers, the shape decision 14 forbids |
| Who the gate applies to | The institution's own members. A program manager passes every action by the `manager` ground, logged as such, including generating a semester report for an unsettled institution | The semester note gated managers; the agreement and members notes did not. The manager is the person the gate is waiting for, and refusing them cannot make an institution's agreement arrive sooner |
| The gate predicate | `WPCPM_Institution_Agreement::is_settled( $record_id )`, true only when a site post in state `accepted` exists **and** Airtable's `Agreement Status` is `Accepted` or `On file`. Any other combination is locked and listed as a discrepancy naming both sides | The agreement note settled on either side alone, so a `Revoked` typed into the grid changed nothing, and a site-side revoke of a legacy row was undone by the next rebuild. Fail closed in both directions |
| Where the gate state is cached | One non-autoloaded option **per institution**, `wpcpm_agreement_<record id>`; revoke and the sync's revoke path delete the row | A single shared map rewritten by every transition under a per-institution lock let two transitions on different institutions interleave and write a stale `settled => true` back over a revoke |
| Cohorts | One class, `WPCPM_Cohort` in `includes/class-wpcpm-cohort.php`, key `2026-H1`, empty bucket `none`, label "January to June 2026", one `participation()` returning seven buckets from which the strip prints two | Two classes with two key formats rejected each other's `wpcpm_cohort` argument and counted `Interested` differently. Decision 11 says one calculation |
| Where the roster lives | A per-institution index option `wpcpm_roster_<record id>` written by the students sync's Students-table pass, and only by it. Counts for the strip and the manager screen come from `wpcpm_roster_counts`, written in the same `finish()` from the same rows | A `WP_User_Query` cannot return a student who has no account, and the `META_START` filter and a second `cohorts` sync phase read the same 800 rows a second time at a second cadence with a second date rule. One pass, one date source (`Students.Start Date`), one read time printed on every surface |
| Generation record | Generating the template creates a `wpcpm_agreement` post in state `generated` (no file), superseded by the upload that follows | Generation facts held only on the cached map were discarded by the daily rebuild, so the reviewer's "name on the document" line was blank for almost every real upload |
| Stage writes from this module | Two, both manager acts: approval (`First Contact Made`) and acceptance (`Confirmed`, forward-only). **Generation writes no stage** | A member pressing Generate at `First Contact Made` would move its own pipeline record past four stages managers use to record outreach |
| PDF | The browser's own Save as PDF over a standalone print document, for both the agreement and the semester report. No bundled renderer | No plugin or theme in `~/GitHub` carries one and Atomic has no headless browser. tFPDF would ship a font bundle, a font-cache write on the generate path and a refusal for any non-Latin name, for one page of boilerplate, while the multi-page document used the browser anyway |
| Import check ceilings and verdicts | `ACTION_CHECK` is behind per-institution ceilings (checks per hour, rows per day); one staged batch per institution; hits outside the institution's own roster collapse to one neutral verdict | An unlimited, verdict-per-row check over the whole base was a membership oracle in bulk: which addresses study elsewhere, which Students rows are adoptable, which addresses hold site accounts |
| Adoption of unlinked Students rows | Not self-service. A program manager links from the reconciliation card, and the control refuses when the row carries a mentor and an automation-matching status or when a Students Reports row already exists for the address | Writing the link onto a row that already has a mentor fires `Add students to Students Reports and Feedback` and creates a second reports row, the duplicate pattern the first spec traced to that automation |
| Consent columns on the Finishing-up form | Saved only when `get_current_user_id() === $student_id`; rendered read-only for a manager; the site stamps evidence only on the self path | `handle_save()` authorised with `user_can_edit()`, which includes `CAP_MANAGE`, so a manager could set "Yes, with my name" for a student. The two existing F3 permission columns had the same hole |
| Reports-only accounts | The stamp comes from the Students row's `Educational Institutions` link when the email join finds one; when it finds none, the reports-side link is used with `institution_source = 'reports'`; when it finds several that disagree, the stamp is deleted and the row counted | Seven accounts exist today with a Students Reports row and no Students row; one rule dropped them from the fence while their mentors still saw them |
| Legacy provisioning mail | The invitation branches on `is_settled()` at send time; per-row provisioning of a Confirmed institution is gated on the recorded agreement state exactly as the bulk button is | A partner that signed years ago would otherwise be emailed that its first step is to sign, and its first login would be the locked panel: decision 24's "forced back through the gate" |
| `Current Stage` on approval, index timing | The approval handler inserts the new record's index row and its `Not started` agreement row immediately after `create_records()` | `attach()` refuses a record not in the index, and the index is rebuilt daily, so every approval's account half would have been refused until the next sync |
| Rejection email | Neutral acknowledgement, no reason. The reason lives on the internal `_wpcpm_app_event` row only | The first spec's Actions table sent the reason verbatim; decision 16 overrides it |
| Saved `student_statuses` on the live site | `WPCPM_Settings::maybe_upgrade()` keyed by a `SETTINGS_VERSION` option, in the shape of `WPCPM_Roles::maybe_upgrade()` | `WPCPM_Updates` is the Report Cards' "Program updates" column (verified: `includes/class-wpcpm-updates.php`), not a migration runner |
| Live Airtable reads for the report | By email, in chunks of 50 through `formula_in( ..., true )`, against the addresses the index holds for the cohort | A name formula built from a PHP `strtolower()` needle never matches a name with an uppercase non-ASCII letter, because Airtable's `LOWER()` is Unicode-aware and PHP's is not; the report would print 0 for Uniwersytet Łódzki |
| Uninstall's index of kept files | Mailed to the site administrator; written to disk only when the last probe recorded the private directory as blocked, under a random name the mail includes | A fixed-name manifest of random filenames in a served directory is a directory listing under another name |
| `Agreement Document` | Holds Drive links only (legacy). A site-accepted row leaves it empty; the site is where the file is | The site moved domains three times on 26 August 2026, and a handler URL without a nonce dies at `check_admin_referer()` |
| Airtable columns on Institutions | Eight, pinned in the fixture, with a test that the constant list length equals the fixture's; `Agreement Signed On` separates the legacy signed date from the site's acceptance date | The note said six and listed seven, and gave `Agreement Accepted On` two meanings |

---

## 3. What the data says

Everything below was read from the base (`appIzQKfwTn5dyPVp`), from the Doc, from Drive, from the reference report and from the live plugin source. It is the evidence the design rests on; nothing here is assumed.

### Institutions (`tbl4V0FEbzRP7I2w2`), 105 records, 37 fields

*Measured on 31 August 2026. Read again on 2 September 2026 for `bin/fixtures/institutions-index-seed.json`: 106 records including the TEST one, 45 fields, and the stages had moved (Student 7, Under Review 5, Call Scheduled 4, no record with an empty stage). Tests pin the fixture's counts, not this table.*

| Current Stage | Records |
| --- | --- |
| Confirmed | 42 |
| Not Moving Forward | 15 |
| First Contact Made | 12 |
| Agreement Sent | 8 |
| Info Sent | 6 |
| Student | 6 |
| Waiting on Reply | 4 |
| Under Review | 3 |
| Call Scheduled | 3 |
| SPAM | 2 |
| Revisit Later | 1 |
| *(empty)* | 3 |

41 of the 42 Confirmed carry a `Contact Email`; one does not, and `wp_insert_user()` cannot make an account without one. No duplicate contact emails and no duplicate names among the Confirmed. Ten names end in a trailing space (`Sorbonne university `, `NSBM Green University `, eight more). Two records have no `Name` (`recCvxvOLHHTHkCnK`, `recFk4EYsN2cftv3b`). Tutors are linked on 3 records. **No field records whether an agreement exists, what kind, when it was accepted or where it is**; `Agreement Sent` is a stage, not a document.

`Current Stage` (`fld4l5x6ScLSLaJZl`) choices are written by name: `update_records()` sends no `typecast`, so a misspelling is a 422 for the whole PATCH. `Confirmed on` (`fldBANuJmJOBRQFs8`) already has a writer, the deployed automation `Institution confirmation date assignment` (`wflajzINJKCehzAQi`), which fills it when the stage reaches `Confirmed` with the date empty. This module never writes it.

### The consent defect, and what it actually is

The brief records 79 institution records carrying the required "Why are you interested" answer and only 16 with `Privacy Policy Compliance` ticked, and reads that as consent lost between form and record. Pulling all 105 records with `createdTime` shows a date boundary instead: every consent-ticked record was created on or after **2026-07-20**; zero records before that date carry consent; zero records on or after it carry a `Why` answer without consent. The checkbox was added to the Airtable form on that date. The form is not dropping the tick.

So the anti-spam layers on the new form are justified as spam control, not consent protection; the server-side consent precondition stays because it is what makes the WordPress record evidential; and the manager report says *N institution records were collected before the consent question was added on 20 July 2026, M of them at Confirmed* (read from the index: 84 and 38 on 2 September 2026, none of them ticked), never the word "lost". Decision 24 answers what to do about the 38: their signed agreements are the basis, recorded per institution in section 7.4.

### The country routing dependency

On 2 September 2026, 138 of the 196 Countries (`tbltB7GSRoTtSi4Ps`) carry `Person of contact (Team)`, resolving to 8 program managers; 58 do not, 3 of which an institution record names (Nigeria, Thailand, Cambodia), so a country without a contact is a routing gap the manager screen lists, never an error. The lookups are spelled `Email (from Person of contact (Team))` and `Calendly link (from Person of contact (Team))`; an earlier draft wrote three closing brackets, and Airtable answers a read for an unknown field name with records carrying no fields rather than an error. The applicant acknowledgement today is sent by `Institutions 1 - Application Received` (`wflD8QmeUqka2JdA4`), a `formSubmitted` trigger embedding the country's Calendly link. A REST create cannot fire it, and under decision 2 no record exists at submission. So the booking link is reproduced by the site from the Countries map, which is the only reason `WPCPM_Countries` reads it. Routing is for the acknowledgement and for information on the queue; there is no per-country queue (decision 15).

### Students Reports (`tbljYkkVGbeoaWEtY`), 795 records

794 carry an institution link (`Educational institution`, lowercase i), none more than one. Graduate 350, In Sensei 217, Not moving forward 163, Dropped out 36, Developer Track 13, Paused 5, In Sensei 50h 5, Pending graduation 4, SPAM 1, blank 1. 621 are inside `student_statuses` plus `past_statuses` today. `WordPress Profile` is on 593 rows (553 distinct handles; 6 handles on more than one row). 18 addresses appear more than once, every pair inside one institution. `Hours` is on 612 rows and read by neither sync.

### Students (`tbla8GZg5x6NY7aWt`), 800 rows, 28 fields (29 since `Site import key` was created on 2 September 2026)

Field names the module reads, exact and case-sensitive: `Full Name`, `Email`, `Status` (14 choices), `Educational Institutions` (**plural, capital I**), `Start Date`, `End Date` (**not** `Internship Start Date`, which is the reports column), `Total hours`, `Your field of study` (9 choices), `Mentor`, `Tutor ` (**trailing space**, `fldC8NoxToMfE4mLd`), `Tutors official`, `WP Profile`, `Accessibility needs`, `Students Reports`, `Privacy Policy Compliance`, `English and proactivity acceptance`.

- 797 of 800 link to exactly one institution; none links to more than one. The three unlinked rows are the only place a link-only "adopt" write can ever apply.
- `Start Date` on 793 (99%), `End Date` on 785, `Total hours` on 576, `WP Profile` on **7**.
- 9 addresses appear more than once, every one inside a single institution; 1 pair differs only by case; 3 rows have no email.
- `Students.Students Reports` and its inverse are **empty on every row of both tables**. The only join is email.
- Status: Graduate 352, In Sensei 197, Not moving forward 171, Dropped out 38, Developer Track 13, Paused 10, empty 9, In Sensei 50h 5, Pending graduation 3, SPAM 1, Fail 1. The column also offers `Interested`, `In Sensei Self-onboarding`, `Duplicated` and one institution name as a status, all at 0.

### The join, measured

758 addresses exist in both tables. On every joined pair the two institution links **agree**; the "10 institutions where the two link counts disagree" (Pisa 16 against 27, UNIFRANZ 66 against 61, Kishoreganj 29 against 22, and so on) are duplicate and orphan rows, not students filed under different schools. Students rows with no reports row: **31** (Not moving forward 15, empty 7, Graduate 6, In Sensei 2, SPAM 1). Reports rows with no Students row: **19** (In Sensei 7, Graduate 6, Not moving forward 4, empty 1, SPAM 1), seven of which have site accounts today. Status disagreements on joined rows: **10**, four of them Universidad Fidelitas rows reading `Paused` on Students and `Not moving forward` on Students Reports.

### Cohorts: exact dates fragment, semester windows do not

There is no cohort, term or intake column anywhere. The first spec measured "46 of 217 students with no start date" **on the site**, which reads `Internship Start Date` from Students Reports, where 128 tracked rows lack it. Read from the Students table, as decision 19 directs, the same population is 197 In Sensei rows with **0** missing a date; 132 joined rows carry a date only on the Students side; and among current students the semester disagrees across the two tables on 0 rows. The "no start date" bucket on the Students table holds **7 of 800** (Not moving forward 4, empty 2, Developer Track 1), and it stays visible for those and for any row that fails the join.

Exact dates fragment: only 41 distinct start dates exist in the whole table, D. Y. Patil has 42 students on 14 dates, and a real September intake scatters across weeks as paperwork clears. Half-years collapse cleanly. Monthly starts across 793 dated rows: 2025-07 1, 2025-09 8, 2025-10 47, 2025-11 40, 2025-12 1, 2026-01 15, 2026-02 161, 2026-03 57, 2026-04 18, 2026-05 260, 2026-06 7, 2026-07 62, 2026-08 76, 2026-09 38, 2026-11 1. Both boundaries fall in troughs (June 7 against July 62; December 1 against January 15), both peaks sit inside one half.

| Semester of `Start Date` | Rows | Current statuses only |
| --- | --- | --- |
| 2023 H2 | 1 | 1 (a D. Y. Patil stray, `2023-07-10`) |
| 2025 H2 | 97 | 9 |
| 2026 H1 | 518 | 52 |
| 2026 H2 | 177 | 165 |
| no start date | 7 | 1 |

20 of 28 institutions sit entirely in one semester. 29 rows start in the future (latest 2026-11-02); for rows created since July 2026 the start sits a median of 8 days after creation. Importing before the start is the normal case.

**Krakow University of Economics is the reference report exactly**: 15 rows, all 2026 H1, Graduate 8, Pending graduation 2, Not moving forward 5. The hand-written report says "15 signed up, 10 graduated". So *signed up* counts everyone including the five who never started, and *graduated* counted the two at Pending graduation. The first is adopted; the second is open question 1.

Krakow calls February to June the *summer* semester; a US institution calls it *spring*. No label in this module ever carries a season word.

### Feedback (`tblx3TH6fp4edQJDm`), 834 rows

`Institution` is linked on all 834. `F3 - One example of a contribution you are proud of` is filled on 289 rows, and every quote in the reference report is recognisably an answer to it. The two existing permission columns are **empty on all 834 rows**: no student has ever recorded any permission to be quoted. `docs/sections/13-student-feedback.md` promises students the surveys "are not marked, your institution never sees them". Section 7.9 keeps that promise.

### The Doc and the Drive folder

The Doc, owned by the Foundation and last modified **2025-11-04**, is: a heading `PLEASE MAKE A COPY` that is an instruction to a human and not part of the agreement; heading, subheading with `[Institution Name]`; two preamble paragraphs, the second "This document is not legally binding..."; sections 1 to 6 (Purpose; Roles and Responsibilities with three bullet lists, one bullet linking the community Code of Conduct; Program Conditions; Liability and Insurance; Duration; Acknowledgement); two signature blocks with Name, Title and Date. **`[Institution Name]` appears exactly twice.** The Doc is **editable by anyone with the link**, so the plugin copy is the only version under change control. The `[EN]` in the title implies siblings in other languages; none was given.

The Drive folder holds about 55 sub-folders, one per institution, named free-form and not matching Airtable names ("Universidad Franz Tamayo (Unifranz) - Bolivia" against "Universidad Privada Franz Tamayo (UNIFRANZ)"), one institution with two folders, two folders that are not institutions, and mixed owners. **"Agreement on file" cannot be auto-matched to Drive.** It is a link a program manager pastes by hand.

### What Airtable does behind our back

The base runs 40-plus automations. These write or mail where this module writes:

| Automation | Trigger | Consequence for this design |
| --- | --- | --- |
| `Add students to Students Reports and Feedback` (`wflXg1xFuiCSG0pXZ`, deployed) | Students row with Full Name, Email, `Educational Institutions` and `Mentor` non-empty and Status in In Sensei / In Sensei Self-onboarding / In Sensei 50h / Developer Track | Creates the Students Reports row (copying Status and both dates, **not** `WP Profile` or `Your field of study`) and a Feedback row. The site creates Students rows only; one creator per table |
| `Update status and start/end date` (`wflUYImI8OEvVuc4R`, deployed) | `recordUpdated` on Students, **restricted to view `viwzSJspvACLnhXom`** "Enrolled + Dropped out + Pending graduation + Graduated (for automation, do not change)", watching Status, dates, Mentor | Rewrites every Students Reports row sharing the email. One authority per field. Paused is not in the view: the four Fidelitas disagreements are what that produces |
| Welcome emails (`wflFkVNm8tTIBZ839`, `wflzQyJJbzZITjcdF`, `wflOC5o1Wh4RcdVJq`) | Students Reports row at a track status with a mentor | The student's first contact from the program follows mentor assignment, not import |
| `Certificate notice after graduation`, `Dropped out notice`, `Not moving forward notice` | Students row reaching that Status | Unretractable mail the log cannot see; every confirm names it |
| `Institutions 1 - Application Received` | `formSubmitted` | Reproduced by the site (section 7.1) |
| `New institution for Pooja` | Institutions with empty stage for one manager | Does not fire for records created here (decision 22) |
| `Institution confirmation date assignment` (`wflajzINJKCehzAQi`, deployed) | `Current Stage` = `Confirmed` and `Confirmed on` empty | Fills `Confirmed on`. This module never writes that column |
| `New partnership confirmed` (`wflCgGw232O5jRHge`, **not deployed**) | `Current Stage` update in view `viwKmGW3nRleeskBu`, posts to Slack | Silent today. If deployed, acceptances on this site post to Slack, which is intended and is named here so nobody reads it as a leak |
| `Student status in institutions table` (`wflhyt8hjHJSlR61Y`) | `Current Stage` = `Student` | Never reached: this module never writes `Student` |

A public form view on the Students table (`viwSn34EJhzg65wFU`, "WordPress Credits - Student Registration Form") lets students create their own rows; its fields cannot be read through the API. An imported student who is then told to register creates a second row. Section 7.6 treats that as the one duplicate source the plugin cannot close.

### Two strings that look like typos and are not

`Anything else you’d like us to know?` uses U+2019. The first internship choice is `" Based on required hours (e.g. 150 hours)"` with a leading space. The Institutions stage is `Not Moving Forward`; the students status is `Not moving forward`. All pinned in fixtures and asserted byte for byte.

### There is no PDF library and no house multipart handler

`grep -rli 'dompdf\|tcpdf\|mpdf\|fpdf\|wkhtmltopdf'` over every plugin and theme in `~/GitHub` returns nothing; Atomic has no headless browser. `grep -rn nopriv includes/` and `grep -rn '\$_FILES' includes/` both return nothing: the application form is the plugin's first logged-out write path and the agreement upload its first file upload. Neither has a pattern to copy, so every check in both is new code and is tested byte-fixture by byte-fixture.

---

## 4. The architecture, in six decisions

**1. A student's institution is a record ID on a queryable key, never a name.** `phase_reports()` today throws the link IDs away and stores a resolved name. A name fails open: `resolve_stored()` returns `''` for an unknown ID, so a name-equality fence matches every unresolved student against every unresolved institution; the lookups map is versioned and empties for a sync cycle on a bump; and the base holds near-collisions and trailing-space variants. So `WPCPM_Students_Sync::META_INSTITUTION = 'wpcpm_student_institution'` holds the record ID, **deleted rather than written empty** (`meta_value => ''` with the default compare matches every row holding an empty string), and `revoke_departed()` clears it, because an identity that outlives the link is a fence that fails open.

**2. The Students table is the institution side's authority, read once, into a per-institution index.** Decision 19. The students sync's existing Students-table pass (`phase_tutors()`, key kept) requests thirteen columns, builds `$state['rows']` keyed by Students record ID, derives each account's `META_INSTITUTION` from the Students row's `Educational Institutions` link by the email join, and `finish()` writes `wpcpm_roster_<record id>` per institution plus `wpcpm_roster_counts`. Every institution-side surface reads the index and prints its read time. Nothing else pages the Students table for the institution side. A `WP_User_Query` cannot return a student with no account, and reading Airtable per render was rejected in the first spec for rate-limit reasons and stays rejected.

**3. Membership is a stamp on the person, and the fence is one policy function.** An institution has no account; people do, and a person's account carries `wpcpm_institution_record_id`. "The members of X" is a query on the stamp, as "the students of X" is. No owner, no tiers, no members list on the institution (two records of one fact disagree). Every path asks `WPCPM_Institution_Policy::decide()` and acts on what it returns, including a `fields` scope that is always `null` today. That scope is the whole trick that keeps joint projects a second clause.

**4. The agreement state is fail-closed on two sources.** Airtable records the state so a manager can work in the grid; the site records the document and the review because Airtable cannot hold a file the way decision 8 requires or attribute a click to a WordPress user. `is_settled()` is true only when both agree. One option per institution caches the answer.

**5. Cohorts are derived at read time from `Students.Start Date` by one class.** Decision 10. The key is never stored on anyone. One `participation()` feeds the roster strip, the manager screen and the semester report, so they cannot disagree about what a number means, only about when it was read, and each says when.

**6. One authority per field, and WordPress holds exactly three things Airtable does not.** The mirror automation rewrites Students Reports from Students on every Students edit, so writing both halves of a mirrored field triggers a revert of the plugin's own first write. Status, both dates and field of study are written to Students; `Name` to Students Reports; `Email` and `Mentor` by nobody on this site. WordPress is the record of consent (the form's, the student's report permissions), of who can sign in to WordPress (membership), and of the signed agreement file. Everything else that WordPress caches is rebuilt from the base.

---

## 5. The authorisation policy

Specified once here. Every write, export, live disclosure and render in sections 7 to 8 calls it and names the action it uses; no handler carries a second copy of any check the policy makes.

### 5.1 `WPCPM_Institution_Members`

`includes/modules/class-wpcpm-institution-members.php`, all static. It owns the stamp: every write to the three stamp keys goes through `attach()` or `detach()`, and a source-match test asserts no other file writes `wpcpm_institution_record_id`.

| Key (user meta) | Meaning |
| --- | --- |
| `wpcpm_institution_record_id` | The membership. Present and well-formed means "this account acts for that institution". Several accounts may carry the same value |
| `wpcpm_institution_active` | 1 while the membership may be exercised. Set to 0 by the sync's revoke and by `detach()` |
| `wpcpm_institution_record_id_was` | Where the stamp goes when membership ends, so history survives and authorisation does not |
| `wpcpm_institution_membership` | `array{ since, by, how, invite }`; `how` is `provisioned`, `approved`, `manager`, `invited`, `legacy`. Facts for the members card and the log; never read by the policy |
| `wpcpm_inst_invited` | Stamped when the login invitation is sent |
| `wpcpm_institution_profile` | name, city, country name, stage, website, contact person, so the header needs no Airtable read |

```php
/**
 * The Airtable institution this account may act for right now, or ''.
 *
 * All three conditions, every time: a well-formed stamp, the active flag at 1, on an
 * account that exists. Revocation moves the stamp and zeroes the flag, so either alone
 * would do; both are checked because they are written by different code paths and the
 * day they disagree is the day one of them is wrong. An empty stamp must never be
 * treated as "matches every student whose institution is also empty", which is the
 * shape of every fence bug in this module's history.
 */
public static function institution_of( $user = null );      // string
public static function memberships_of( $user = null );      // string[]: one element or none today; a list because the policy iterates it
public static function is_member( $user = null );           // bool; what WPCPM_Notices::applies_to() delegates to
public static function members_of( $record_id );            // WP_User[]; guarded here: a malformed ID returns nothing and issues no query
public static function former_members_of( $record_id );     // the _was key
public static function attach( $user_id, $record_id, $how, $actor_id, $invite_id = 0 );  // true|WP_Error
public static function detach( $user_id, $reason, $actor_id );                           // true|WP_Error
```

`attach()`: the record is well-formed **and in the pipeline index** (an institution the site has never read cannot acquire members; the approval handler inserts the row before it attaches); the account exists; identity rules, each a named `WP_Error`: an administrator is refused (a manager passes by the `manager` ground and stamping one would make the log's ground ambiguous); an account carrying `wpcpm_student_record_id` is refused (the school sees the student, not the other way round); a mentor is allowed with `add_role()`, never `set_role()`; a live member of this institution is "already a member"; a live member of another institution is refused (one membership per account today); a former member is re-added and `_was` cleared. Then the stamp, the flag, the facts, `add_role( ROLE_INSTITUTION )`, one audit row `member_added`.

`detach()`: move the stamp to `_was` (delete, never blank); flag 0; remove `ROLE_INSTITUTION` unless administrator, `subscriber` if no role remains; cancel every pending invitation this account issued (a no-op until the invitation post type ships in Phase 4); keep the account, it is a person; one audit row `member_removed` with the reason (`removed`, `left`, `revoked`); and when no live member remains, the last-member rule: cancel every pending invitation for the institution and notify the managers through `WPCPM_Institutions::notify_managers()` (section 7.4). The sync's revoke phase calls `detach( $id, 'revoked', 0 )` per member rather than carrying its own copy of the steps.

**The sync provisions from `Contact Email` only for an institution with no membership history** (no live member and no `_was` naming it). After the first account, membership is managed on the site; a `Contact Email` that matches no live member is counted on the manager screen as "contact not a member" with a one-click add beside it. Without this rule the sync re-creates a removed contact's account every night, forever.

### 5.2 `WPCPM_Institution_Policy`

`includes/modules/class-wpcpm-institution-policy.php`, all static, no state, no HTTP, no reads of the request. It reads exactly two things it is not handed: the acting user's memberships, and the acting institution's agreement state.

```php
final class WPCPM_Institution_Policy {

    const GROUND_MANAGER = 'manager';
    const GROUND_MEMBER  = 'member';
    // Reserved, not built: 'project'. See section 12.

    const ACT_VIEW_ROSTER          = 'view_roster';           // the dashboard, its groups, the strip, the members card
    const ACT_VIEW_STUDENT         = 'view_student';          // one student's detail view, from cache
    const ACT_VIEW_REPORT          = 'view_report';           // the live Student Report Card panel and the REST report route
    const ACT_EDIT_STUDENT         = 'edit_student';
    const ACT_CHANGE_STATUS        = 'change_status';         // graduate, withdraw
    const ACT_ADD_STUDENT          = 'add_student';           // import: check, confirm, continue, cancel
    const ACT_EXPORT               = 'export';                // both CSVs
    const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
    const ACT_EDIT_SEMESTER_REPORT = 'edit_semester_report';  // generate, save, restore, final, reopen, ask, print
    const ACT_MANAGE_MEMBERS       = 'manage_members';        // invite, cancel, resend, remove
    const ACT_AGREEMENT            = 'agreement';             // view, generate, upload, withdraw, download. The only ungated action

    const REFUSAL_CODE = 'wpcpm_inst_unknown';

    /**
     * The full map, not a default plus exceptions. Adding a ground to an action is a visible
     * one-line diff here and a failing assertion until the expected map is updated in the
     * same commit. That is how a project clause will land on two rows and on nothing else.
     */
    public static function grounds() { /* every ACT_* => array( GROUND_MANAGER, GROUND_MEMBER ) */ }

    /** Actions the agreement gate does not apply to. One, by decision 6. */
    public static function ungated() { return array( self::ACT_AGREEMENT ); }
```

**Subjects** carry their own evidence; the policy never fetches.

```php
// array{ type: 'institution'|'student'|'report'|'agreement'|'semester_report'|'batch', id, institution_ids: string[], evidence: 'index'|'cache'|'live' }
public static function subject_institution( $record_id );          // ids = array( $record_id ); evidence 'index'
public static function subject_student_account( $user_id );        // ids from META_INSTITUTION; evidence 'cache'
public static function subject_index_row( $record_id, $students_record );   // ids from the roster index; evidence 'cache'
public static function subject_post( WP_Post $post, $meta_key );   // ids from the post's own institution meta; evidence 'cache'
public static function subject_live( $type, $id, array $ids );     // built by claim() after a live read; evidence 'live'
```

`subject_post()` is how every post-keyed handler (agreement download, withdraw, accept, return, revoke; batch confirm and cancel; report save and print) names its institution: **from the post's own meta, never from the form**. A member of B posting A's post ID is decided against A, is not a member of A, and gets the one refusal.

```php
    /**
     * May this user perform this action on this subject, and on what grounds.
     *
     * The one fence. It never sees the request and never makes an HTTP call; evidence is the
     * caller's job, at the level the action deserves. `fields` is null for every shipped
     * ground and is read by every caller anyway: a field-scoped ground added later narrows
     * every renderer and handler without touching one.
     *
     * @return array{ allowed: bool, ground: string, institution: string, fields: string[]|null, why: string }
     *   `why` goes to the log and nowhere else; the user-facing refusal is one message.
     */
    public static function decide( $action, array $subject, $user = null ) {
        $refused = array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => '' );
        $grounds = self::grounds();

        // An action the map does not know is refused, not allowed. A handler with a typo in
        // its action fails its own happy-path test rather than passing every user.
        if ( ! isset( $grounds[ $action ] ) ) {
            return array_merge( $refused, array( 'why' => 'unknown-action' ) );
        }

        $user = WPCPM_Roles::resolve_user( $user );
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return array_merge( $refused, array( 'why' => 'no-user' ) );
        }

        // Shape first, on the subject's side. An empty list is an institution-less subject,
        // which belongs to nobody but a manager.
        $ids = array_values( array_filter( (array) $subject['institution_ids'], array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

        foreach ( $grounds[ $action ] as $ground ) {
            $decision = call_user_func( array( __CLASS__, 'ground_' . $ground ), $action, $subject, $ids, $user );
            if ( is_array( $decision ) ) {
                return $decision;
            }
        }

        return array_merge( $refused, array( 'why' => 'no-ground' ) );
    }

    /**
     * CAP_MANAGE. The unconditional override every path in this plugin keeps. Not gated by
     * the agreement: the gate limits what an institution may do for itself, and the manager
     * is the person the gate is waiting for. The decision is logged as `manager`, so a
     * manager's semester report for an unsettled institution is a manager's act on the record.
     */
    private static function ground_manager( $action, array $subject, array $ids, WP_User $user ) { ... }

    /**
     * Membership of an institution the subject belongs to. Two empties never meet: the
     * subject's list was filtered and is tested for emptiness, and institution_of() returns
     * '' rather than an empty-but-present stamp. The agreement gate lives here and not in
     * the handlers, so a handler cannot forget it.
     */
    private static function ground_member( $action, array $subject, array $ids, WP_User $user ) {
        if ( empty( $ids ) ) {
            return null;
        }
        foreach ( WPCPM_Institution_Members::memberships_of( $user ) as $mine ) {
            if ( ! in_array( $mine, $ids, true ) ) {
                continue;
            }
            if ( ! in_array( $action, self::ungated(), true ) && ! WPCPM_Institution_Agreement::is_settled( $mine ) ) {
                continue;
            }
            return array( 'allowed' => true, 'ground' => self::GROUND_MEMBER, 'institution' => $mine, 'fields' => null, 'why' => '' );
        }
        return null;
    }

    /**
     * "Not yours", "no such record", "not a member" and "agreement outstanding": one WP_Error,
     * byte for byte. A form that answered differently would be a membership oracle an
     * institution could walk to learn which record IDs are real students elsewhere.
     */
    public static function refusal() {
        return new WP_Error( self::REFUSAL_CODE, __( 'That record is not on your roster.', 'wpcredits-program-manager' ) );
    }

    /** Narrow keyed cells, columns or rows to what the decision permits. Keys are "<table>|<field>". null permits everything. */
    public static function scope( array $decision, array $keyed ) { ... }
}
```

The `array_merge()` is deliberate: `+` keeps the left operand and one day somebody swaps the operands (the first spec records the approval payload bug that shape produced).

### 5.3 `owns()` and `claim()`

They keep their names and their two levels and lose their comparisons.

```php
/** Cache-level decision, for rendering. No HTTP. */
public static function owns( array $subject, $action, $user = null )   // -> decision array

/**
 * Live decision, for anything that writes or discloses a live record.
 *
 * @param string $record Airtable record ID.
 * @param string $type   'student' (a Students row) or 'report' (a Students Reports row).
 * @return array{ record: array, decision: array }|WP_Error
 */
public static function claim( $record, $action, $type = 'student', $user = null ) {
    // 1. Shape first, before anything reaches the network. A 4KB paste must not become a request.
    if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
        return WPCPM_Institution_Policy::refusal();
    }
    // 2. The cheap decision from what the site holds: an account's stamp, or the index row for
    //    a Students record with no account. Nothing cached is an institution-less subject, which
    //    a member cannot pass and a manager can. A refusal here spends nothing.
    $pre = WPCPM_Institution_Policy::decide( $action, self::cached_subject( $record, $type ), $user );
    if ( ! $pre['allowed'] ) {
        return WPCPM_Institution_Policy::refusal();
    }
    // 3. The live read of the Students row, resolved from a report ID when that is what was
    //    given: the report's `Students` link if present, else LOWER({Email}) = LOWER(%s). Zero
    //    or two-plus matches is a refusal, fail closed. A WP_Error is returned, never swallowed.
    $row = self::live_students_row( $record, $type );
    if ( is_wp_error( $row ) ) {
        return $row;
    }
    // 4. The authoritative decision, on the Students row's `Educational Institutions` link. The
    //    reports row's own link is not consulted: decision 19 says which side wins.
    $decision = WPCPM_Institution_Policy::decide( $action, WPCPM_Institution_Policy::subject_live( $type, $record, WPCPM_Airtable::link_ids( $row['fields']['Educational Institutions'] ?? array() ) ), $user );
    if ( ! $decision['allowed'] ) {
        self::log_live_refusal( $record, $decision, $user );   // the one refusal that is logged: the subject resolved
        return WPCPM_Institution_Policy::refusal();
    }
    return array( 'record' => $row, 'decision' => $decision );
}
```

Steps 1 and 2 count into a per-acting-user ceiling that locks that account's write path and raises a manager notice; only step 4 writes a log row. Refusal logging of cheap shapes was a denial of service in an earlier design and stays out.

### 5.4 The three call patterns

**A render from cache** (dashboard, detail view, members card, batch preview, report edit view):

```php
$institution = WPCPM_Institution_Roster::resolve_institution( $viewer, $can_manage );
$decision    = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_VIEW_ROSTER, WPCPM_Institution_Policy::subject_institution( $institution ), $viewer );
if ( ! $decision['allowed'] ) { /* the empty or locked state, never a list, never the refusal text */ }
$columns = WPCPM_Institution_Policy::scope( $decision, self::columns() );
```

**A write or a live disclosure** (save, status change, the report route, the single-student export):

```php
// Nonce first: claim() may make an HTTP request, and a cross-site POST must not cause one.
check_admin_referer( self::ACTION_SAVE . '_' . $subject );
$claim = WPCPM_Institution_Roster::claim( $subject, WPCPM_Institution_Policy::ACT_EDIT_STUDENT );
if ( is_wp_error( $claim ) ) { self::bounce( $claim ); }
$cells = WPCPM_Institution_Policy::scope( $claim['decision'], $cells );
```

**A manager-side handler** (approve, add account, remove, provision, accept, return, revoke, on-file): `verify()`'s order, capability then nonce, then `decide()` all the same, so the log row says `manager` rather than nothing.

`bin/test-institution-policy.php` asserts at source level that every `admin_post_` handler registered by the institution classes contains `decide(` or `claim(` before its first `WPCPM_Airtable` call, and that no file under `includes/modules/` other than the policy compares institution IDs (`/wpcpm_(student_)?institution(_record_id)?[^;]*===/` matches nothing).

### 5.5 Which institution the viewer is

`resolve_institution( WP_User $viewer, $can_manage )` returns a **record ID**, because with several members per institution a user is the wrong unit: under `CAP_MANAGE` only, `wpcpm_institution_view`, read with `WPCPM_Request::text()` and not `key()` (`sanitize_key()` lowercases and a record ID is case-sensitive), accepted when `is_record_id()` and present in the pipeline index; then `institution_of( $viewer )`; then, for a manager alone, the first institution in the index with a live member. The switcher lists institutions, one entry each. This is also the acting institution for every manager-on-behalf action (import, generate, upload), never `institution_of()`, which is `''` for every manager because `attach()` refuses administrators. A handler whose resolved institution is `''` refuses outright.

### 5.6 The audit log carries the ground

`wpcpm_audit_entry` (a private post type in the shape of `WPCPM_Mentor_Notes`, applied changes only, one row per save, no count cap, no IPs) gains `_wpcpm_log_ground` (`manager` | `member`) and `_wpcpm_log_evidence` (`index` | `cache` | `live`) on every row. Membership and agreement events are rows of the same type. Its docblock says the log cannot see the automations, so "last write wins is detectable after the fact" is not true for Status, the two dates and Mentor.

---

## 6. The one lifecycle, and where each step is specified

| Step | Section | Actor | What exists afterwards |
| --- | --- | --- | --- |
| Apply | 7.1 | a stranger | a held `wpcpm_inst_app` post, a verification mail, an acknowledgement with the booking link |
| Reviewed | 7.2 | a program manager | a decision; a neutral or silent close, or a question |
| Approved | 7.3 | a program manager | an Airtable record at `First Contact Made`, an index row, an agreement row at `Not started`, one account attached, one invitation |
| Agreement | 7.4 | members and a manager | a `wpcpm_agreement` post in `accepted`, Airtable `Accepted` or `On file`, `Current Stage` at `Confirmed`, the account open |
| Unlocked | 7.5 | members | the dashboard, the roster groups, the members card |
| Import | 7.6 | members, or a manager on behalf | Students rows, index rows, `add` requests |
| Track | 7.7 | members | the cohort filter and the comparison strip |
| Edit | 7.8 | members | Students and Students Reports cells, audit rows |
| Report | 7.9 | members | a `wpcpm_inst_report` post, its revisions, a printed copy |
| Graduate | 7.10 | members | a Students status, an Airtable email, an audit row |
| Leave | 7.11 | the sync, members, a manager | stamps moved, files kept |

---

## 7. The lifecycle

### 7.1 Apply: the public form

`WPCPM_Institution_Application` in `includes/modules/class-wpcpm-institution-application.php`, block plus shortcode plus a provisioned page copying `WPCPM_Students_Dashboard::register()`. **The page is not gated**: absent `_wpcpm_access_level` means public, and a gated application form is one nobody can reach; that comment goes on the line, because copying the neighbouring class is the obvious mistake and it fails silently. The editor preview is static (a summary of groups and the country count under `REST_REQUEST`), never a live form minting tokens on every keystroke.

**The thirteen fields**, keyed by exact Airtable column name and hashed to form keys (`'f' . substr( md5( $name ), 0, 12 )`, because one name carries U+2019):

| # | Column | Group | Required | Control |
| --- | --- | --- | --- | --- |
| 1 | `Name` | About your institution | yes | text, 200, `sanitize_text_field( mb_substr() )` |
| 2 | `Country` | About your institution | yes | `<select>` of 196, value the `rec…` ID validated against `WPCPM_Countries::options()`, stored `array( $id )` |
| 3 | `City` | About your institution | yes | text, 120 |
| 4 | `Website` | About your institution | yes | `type="text" inputmode="url"` plus `clean_url()`; `type="url"` refuses the scheme-less addresses the base is full of |
| 5 | `Contact Person` | Who we should contact | yes | text, 120 |
| 6 | `Contact Email` | Who we should contact | yes | `sanitize_email()` then `is_email()`; reject, never clear |
| 7 | `Department` | Who we should contact | yes | text, 150 |
| 8 | `How do your internships or practices typically work?` | How your program works | at least one | checkbox list against `internship_choices()`, the first with its leading space |
| 9 | `Comments` | How your program works | when "Other" is ticked | textarea, always rendered |
| 10 | `Estimated number of students who may be interested` | How your program works | yes | integer 1 to 10000 |
| 11 | `Why are you interested in offering WordPress Credits to your students?` | Tell us more | yes | textarea, `MAX_TEXT` |
| 12 | `Anything else you’d like us to know?` | Tell us more | no | textarea; U+2019 in the key |
| 13 | `Privacy Policy Compliance` | Before you send this | must be true | see consent |

`clean()` returns `array{ ok, value, problem }`; the handler walks `fields()` and never `$_POST`; requiredness is checked once after cleaning.

**Consent is a precondition, not a stored value.** The key absent, `"0"` and any value other than `"1"` or `"true"` refuse the whole submission. `_wpcpm_app_consent` records the rendered sentence, `get_privacy_policy_url()`, the policy post ID, the policy's `post_modified_gmt` (the point: "they agreed" is worthless if the policy changed), the time, a truncated IP and the agent. If no privacy policy page is set the form refuses to render.

**Anti-spam**, justified as spam control on the plugin's first `nopriv` write path: a honeypot named `wpcpm_confirm_url` (not `website`, which a password manager would fill for a human); a signed, nonce-bound, single-use dwell token (`MIN_SECONDS` 6 held as spam, over 12 hours stale not spam); a per-actor limiter that refuses at 5 an hour and a site-wide one that **degrades** at 40 a day (accepts, holds, sends nothing), keyed by `wp_hash( $ip )` with `add_option()` claims per bucket; content scoring (disallowed list, URL count, identical or short answers, no MX, name equals contact) held not refused, with `_wpcpm_app_signals` saying why; and **duplicate suppression that never merges**, because a stranger submitting first with a target's published address must never be able to edit or suppress the genuine submission. `client_ip()` trusts a forwarded header only from the known edge, which is open question 9.

**The submit path**: `wp_verify_nonce()` and never `check_admin_referer()` (a stranger with an overnight tab must not meet a 403 death screen with three paragraphs gone); on any non-spam failure the cleaned values go into a 30-minute transient and consent is never repopulated; the confirmation is rendered from a one-shot stash, not from a reference in the URL, so the reference is not a bearer token for the applicant's address.

**Mail**, both through `WPCPM_Mail`. `send_to( $email, $context, $build, $locale )` is added beside `send()` as its own commit with `bin/test-mail.php` cases, because it touches the single exit for all plugin mail. `institution-applied` to the applicant carries the reference, what happens next, **the routed manager's Calendly link** from `WPCPM_Countries::routing()`, and the address-verification link (a `wp_hash()`-signed single-use token that stamps `_wpcpm_app_verified`). `institution-application` to the managers with a link to the queue row, for submissions that pass the checks; held rows wait silently **for the managers only**. Corrected in Phase 3 (1.73.0): the applicant is written to for any row that is not spam, because the acknowledgement carries the link that stamps `_wpcpm_app_verified`, and approval refuses an unverified row for ever. Gating that mail on `new` made every held application permanently unapprovable, which is not what holding was for. Since held rows are now acknowledged, the site-wide degrade is no longer a cap on outbound mail either, so the send has a ceiling of its own (`MAIL_PER_DAY`, deliberately far above `PER_DAY` so it never silences the rows it exists for); past it the row is stored and queued with `mail-ceiling` against it and the applicant is told plainly that no message was sent.

On `template_redirect` for this page only: `nocache_headers()` and `DONOTCACHEPAGE`.

### 7.2 Reviewed: the queue

`WPCPM_Institutions::render_admin_page()` stops falling through to `render_placeholder()`. One page, view state in the query string, markup keeping `div.wrap.wpcpm-wrap > h1 > p.wpcpm-lede > .wpcpm-card`. The `[data-wpcpm-progress]` selector in `assets/js/admin.js` is widened, because this screen draws a sync panel and a provisioning run.

**Storage**: `wpcpm_inst_app`, `post_status` always `private`, `post_author` 0 for a logged-out submission, `post_title` the institution name. Meta: `_wpcpm_app_fields` (immutable after insert, and never containing `Country`, `Current Stage` or `Privacy Policy Compliance`, which are server-held), `_wpcpm_app_state` (`new`, `held`, `spam`, `info`, `approved`, `rejected`), `_wpcpm_app_reference`, `_wpcpm_app_country` and `_wpcpm_app_country_name`, `_wpcpm_app_manager` (snapshot of `routing()`), `_wpcpm_app_consent`, `_wpcpm_app_signals`, `_wpcpm_app_email` (`wp_hash()` of the lowercased address, for duplicate flagging only), `_wpcpm_app_verified`, `_wpcpm_app_record`, `_wpcpm_app_user` (the **first member** approval created), `_wpcpm_app_event`.

**The "Waiting for review" card** is one list, oldest first, of two kinds of row: applications and agreement uploads (section 7.4), each with `human_time_diff()` age, the absolute date, an `is-overdue` class past `agreement_review_days` (3), the country and its `Person of contact (Team)` labelled *for information*, and the possible-duplicate flag. The Institutions menu entry carries a pending-count bubble: `WPCPM_Module` gains `menu_label()` defaulting to `label()`, the Institutions module returns the label plus `<span class="awaiting-mod count-N"><span class="pending-count">N</span></span>`, `WPCPM_Admin::register_menu()` passes `menu_label()` as the submenu title, and `label()` stays plain because it is also the `<h1>`.

The open application shows all thirteen answers escaped, the consent sentence with its timestamp and wording, the verification state, and a live duplicate search that compares `TRIM(LOWER({Name}))` and `LOWER({Contact Email})`, expecting outreach-promoted records with no `Contact Email` at all.

| Action | Handler | Airtable | Account | Email |
| --- | --- | --- | --- | --- |
| Approve | `ACTION_APPROVE` | create at `First Contact Made` | create and attach | one invitation |
| Request more information | `ACTION_INFO` | none | none | the manager's question, reply-to the manager |
| Reject | `ACTION_REJECT` | none | none | **a neutral acknowledgement with no reason** (decision 16); the reason is stored on `_wpcpm_app_event` only. This changes the first spec's Actions table |
| Reject as spam | `ACTION_SPAM` | none | none | **nothing**: the address is forged or a probe, and either way a reply is wrong |
| Reopen | `ACTION_REOPEN` | none | none | none; the abort that exists after the mistake |
| Purge | `ACTION_PURGE` | none | none | none |

Every handler opens capability first, nonce second (`WPCPM_Students::verify()`'s order), nonce keyed to the application ID. Retention as settings (`application_spam_days` 30, `application_rejected_days` 365, `application_approved_days` 0 meaning never) by a daily `wpcpm_purge_applications` cron using `wp_delete_post( $id, true )`, each purge writing one row to a capped `wpcpm_application_log` with no address and no free text.

### 7.3 Approved: the record, the account, and the one thing it can do

Approve carries the confirm treatment `render_invite_card()` uses: *Create an Airtable record and a site account for Universidad Example, and email a password-set link to contact@example.edu? The Airtable record cannot be removed from here.*

```
approve( $application_id ):
  1. capability, then nonce keyed to the application
  2. load the post; 404 unless ours; refuse unless state is new, held or info
  3. refuse unless _wpcpm_app_verified is set          // approval must never mail a password link to an address nobody claimed
  4. add_option() lock keyed to the application, LOCK_TIMEOUT 60
  5. validate the contact email; refuse if get_user_by( 'email' ) finds ANY account
     // Found by email with no institution stamp is a conflict, not a match. provision_mentor()'s
     // conflict test only fires on a DIFFERENT stamp of the same kind, so a student, a mentor or a
     // hand-made administrator would otherwise be adopted and mailed a password link.
  6. re-read the country; refuse if it no longer resolves
  7. AIRTABLE HALF: _wpcpm_app_record stamped and shape-valid -> skip
       else trimmed duplicate search; hit -> adopt; no hit -> create_records() with
         array_merge( $stored, array(
             'Country'                   => array( $country_id ),
             'Current Stage'             => 'First Contact Made',      // decision 22
             'Privacy Policy Compliance' => true,                       // a PHP boolean; the column is a checkbox and typecast is off
             'Agreement Status'          => 'Not started',              // section 7.4, T1
         ) )
       stamp _wpcpm_app_record immediately
  8. INDEX HALF: insert the record's row into wpcpm_institutions_index and write
     wpcpm_agreement_<record> at summary `none`, the way apply_report() bridges the sync gap.
     attach() refuses a record the index does not hold, and the index is rebuilt daily.
  9. ACCOUNT HALF: _wpcpm_app_user stamped and the user exists -> skip
       else wp_insert_user() with ROLE_INSTITUTION; attach( $id, $record, 'approved', $manager ); stamp _wpcpm_app_user
 10. state approved, event row, the invitation, release the lock
```

`array_merge()` and not `+`: `+` keeps the left operand, `$stored` would contain the applicant's own `Country` and consent value, and the checkbox column would 422 the whole record on a string. Airtable first, because the record ID is the account's identity and an account with no institution is the shape the fence cannot tolerate. There is no "partially approved" state: an application in `new` carrying a stamped record renders a half-done banner with "press Approve again to finish", and every half is stamped the moment it lands.

**The invitation** is `WPCPM_Mail::welcome_email()`'s new institution branch, context `invite-institution`, previewed in tests by running the real filter against a stand-in body. It branches on `is_settled( $record )` at send time: for a new institution, *the first and only step is the Collaboration Agreement: generate ours or upload yours, sign it, upload the signed copy; a program manager then accepts it and the rest of the site opens*; for an institution whose agreement is on file, *your agreement is on file and your account is open*, with the dashboard link. `drain_queue()`'s role-to-meta map and `queue_invites()`'s already-invited test gain a third arm with `wpcpm_inst_invited`, in the same commit.

### 7.4 The agreement gate

The account created in 7.3 can log in and do one thing. `WPCPM_Institution_Agreement` in `includes/modules/class-wpcpm-institution-agreement.php`, all static, in the shape of `WPCPM_Mentor_Notes`.

#### The predicate

```php
/**
 * Whether this institution's Collaboration Agreement is settled right now.
 *
 * Reads one non-autoloaded option, `wpcpm_agreement_<record>`, and nothing else: no HTTP, no
 * post query, so it can sit inside ground_member() on every request. The row is true only
 * when BOTH sources agree: a wpcpm_agreement post in state `accepted` exists for the record,
 * and Airtable's `Agreement Status` read by the last sync (or written by the last site
 * transition) is `Accepted` or `On file`. An absent row, a malformed row, a row at the wrong
 * version, or the two sources disagreeing is locked and listed on the manager screen as a
 * discrepancy naming both sides. Fail closed in both directions, because a manager typing
 * `Revoked` into the grid must lock, and a site-side revoke must not be undone by the next
 * rebuild.
 */
public static function is_settled( $record_id );   // bool, never an exception
```

The option holds `array( 'v' => 1, 'settled' => bool, 'site_state' => 'accepted'|'submitted'|..., 'airtable_status' => 'Accepted'|..., 'kind', 'agreement_id', 'pending_id', 'generated_id', 'accepted_at', 'updated' )`. Every site transition rewrites **its own institution's option** under `add_option( 'wpcpm_agreement_lock_<record>' )`; the institutions sync's `records` phase and the Refresh button rebuild every option from Airtable plus the posts (T12); revoke and the sync's revoke path **delete** the option, so a lost write leaves the institution locked rather than open.

#### Document states and the institution summary

Each document is a `wpcpm_agreement` post (section 9) with `_wpcpm_agr_state`: `generated` (template generated, no file), `submitted`, `accepted`, `returned`, `withdrawn`, `superseded`, `revoked`. At most one post per institution is `accepted`. The summary the panel and the queue name: `none`, `generated`, `submitted`, `returned`, `revoked`, `accepted` (kind `template` or `own`), `on_file` (kind `legacy`). Only the last two settle, and only when Airtable agrees.

#### Every transition

| # | Name | Actor | From | To | Airtable write (first, refuse on failure, except where marked) | Mail |
| --- | --- | --- | --- | --- | --- | --- |
| T1 | provision | approval or legacy provisioning | nothing | `none` | approval adds `Agreement Status` = `Not started` to its create; legacy writes nothing | `invite-institution` |
| T2 | generate | member, or manager on behalf | `none`, `generated`, `returned`, `revoked` | post `generated` (no file; name, version, language, actor recorded) | `Agreement Status` = `Template generated` when empty or `Not started`; `Agreement Kind` = `Program template`; `Agreement Template Version`. **No stage write.** Failure does not stop the download: `_wpcpm_agr_airtable_pending`, the sync retries | none |
| T3 | upload | member, or manager | any without a `submitted` post | post `submitted`; a `generated` post becomes `superseded` | `Agreement Status` = `Awaiting review` unless an `accepted` post stands; `Agreement Kind`; `Agreement Submitted On`. Failure does not fail the upload; pending flag | `agreement-received` to every member; `agreement-landed` to managers |
| T4 | withdraw | member, or manager | post `submitted` | `withdrawn`; file deleted at once | summary recomputed | none |
| T5 | accept | manager | post `submitted` | `accepted`; previous `accepted` becomes `superseded` | `Agreement Status` = `Accepted`, `Agreement Kind`, `Agreement Accepted On`, `Agreement Accepted By`, `Agreement Template Version`; `Current Stage` = `Confirmed` forward-only | `agreement-accepted` to every member |
| T6 | return | manager, note required | post `submitted` | `returned` | `Agreement Status` = `Returned` unless an `accepted` post stands | `agreement-returned`, note verbatim |
| T7 | record on file | manager | no `accepted` post | new post kind `legacy`, state `accepted`, Drive link, no file | `Agreement Status` = `On file`, `Agreement Kind` = `Legacy`, `Agreement Document` = Drive link, `Agreement Signed On` (entered), `Agreement Accepted On` = today, `Agreement Accepted By` | none |
| T8 | revoke | manager, note required | post `accepted` | `revoked`; option deleted | `Agreement Status` = `Revoked`; stage untouched (the plugin does not guess `Not Moving Forward`) | `agreement-revoked`, note verbatim |
| T9 | reinstate | manager | the most recently `revoked` post, no `accepted` standing | `accepted`; option rewritten | `Agreement Status` back to `Accepted` or `On file` | `agreement-accepted`, reinstated wording |
| T10 | replace | member, or manager | an `accepted` post stands | a second post `submitted` alongside | `Agreement Submitted On` only; status stays | as T3 |
| T11 | discard | daily cron | `withdrawn`, `returned` older than `agreement_discard_days` | file deleted, post kept | none | none |
| T12 | reconcile | sync `records` phase, Refresh | any | every option rebuilt; an Airtable `On file` row with a Drive link and no `accepted` post **materialises a legacy post** (`post_author` 0, event `recorded in Airtable`) so "an accepted one stands" has one meaning for T3, T6 and T10 | none | none |

T5, T7, T8 and T9 all write Airtable first and refuse on failure, for the reason the first spec gives for approval: Airtable is the system of record for the state, and an institution admitted on the site with a base that says `Awaiting review` is the shape the whole design exists to prevent. If the PATCH lands and the process dies before the post changes, the next reconcile lists the disagreement with the post that was in review; pressing Accept again rewrites identical values and completes. Idempotent by construction.

`STAGE_ORDER` is `First Contact Made, Call Scheduled, Info Sent, Waiting on Reply, Under Review, Agreement Sent, Confirmed, Student`. A stage write may only move forward along it and never off the end; `Student` is left alone; `Not Moving Forward`, `SPAM` and `Revisit Later` refuse acceptance by name ("change the stage in Airtable first"), because acceptance is the program saying yes and it must not say yes to a record it has said no to. Written as a list so the base's spelling is asserted once in the fixture.

#### What a locked account sees

`WPCPM_Institutions_Dashboard::render()` branches immediately after `resolve_institution()`: when `! $can_manage && ! is_settled( $record )`, it draws the identity header, then `render_panel()`, then nothing. Not the summary strip, not the filter bar, not the roster footer: an account at this stage has no roster and a "0 students" line would read as data loss. A manager viewing a locked institution through the switcher sees the full dashboard under a banner: *This institution's agreement is not settled: <state>. Members see only the agreement panel.* Hiding is a courtesy, not the gate: `?wpcpm_institution_student=123` on a locked account reaches `decide( ACT_VIEW_STUDENT )`, whose member ground refuses inside `ground_member()`.

| Summary | Panel | Manager row |
| --- | --- | --- |
| `none` | Three numbered steps. Step 1: **Generate the program's agreement** (name as it will print, language when more than one template exists) or **Upload your own agreement** ("a program manager reads an institution-specific agreement in full, so this path takes longer"). Steps 2 and 3: sign, upload the signed PDF | "Not started", with the account's creation date |
| `generated` | Plus "You generated the agreement on <date> (template <version>)" | "Template generated <age>" |
| `submitted` | "We received your signed agreement on <date>. A program manager will review it and you will get an email either way." Download own copy. Withdraw | in the queue, oldest first, with age |
| `returned` | The manager's note verbatim, dated and signed, then the upload form | "Returned <age>: <excerpt>" |
| `revoked` | The note verbatim, the upload form, the country contact's address | "Revoked <age>", Reinstate |
| `accepted`, `on_file` | The full dashboard, with a card at its foot: accepted on <date>, Download, replace by uploading a new signed copy; the current one stays in force | "Accepted <date>" or "On file (Drive)", Download or the Drive link, Revoke |

#### The template, and keeping it in step with the Doc

`includes/templates/collaboration-agreement-en.php` returns a structured block list (`h1`, `h2`, `p`, `h3`, `label`, `ul`, `signatures`), not HTML, with `language`, `version` (`2025-11-04`, the Doc's `modifiedTime` when the copy was taken), `read` and `source`. The text is copied byte for byte including curly quotes, with one change: the Code of Conduct link is printed as its words followed by the address in parentheses, because a signed paper copy cannot be clicked. `PLEASE MAKE A COPY` is omitted.

`WPCPM_Agreement_Template` (`includes/class-wpcpm-agreement-template.php`): `PLACEHOLDER = '[Institution Name]'`, `OCCURRENCES = 2`, `load( $language )`, `languages()`, `merge( $template, $name )` (refuses with `WP_Error` if any `[...]` survives, so a template edit that introduces a second placeholder prints "the template needs attention; a program manager has been told" rather than `[Signatory Title]` on a document a rector signs), `plain_text()`, `version()`, `drift()`.

`bin/fixtures/agreement-template-en.json` holds the version, the `sha256` of `plain_text()`, the placeholder count and the load-bearing sentences. Any edit without a version bump and a fixture refresh fails CI.

**Two named owners, four steps.** The wording owner is the program (the holder of `info@wordpressfoundation.org`, or whoever the Education team lead names): the Doc is the master for what the agreement says. The plugin copy owner is the developer: the plugin copy is the master for what the site generates. (1) The wording owner changes the Doc and tells the developer, naming the change. (2) The developer updates the block list, bumps `version` to the Doc's new modified date, refreshes the fixture, runs the test, and lists the change in `readme.txt`. (3) The manager screen's template card shows the plugin copy's version and read date beside the Doc's link and a **Check against the Doc** button: `drift()` fetches `export?format=txt`, normalises both sides, and shows differences through `wp_text_diff()`. A button only, never a schedule, never a reason to refuse generation: the Doc is world-editable and a drift report could be vandalism. (4) Institutions that signed an earlier version are listed per `Agreement Template Version`, so re-signing is a program decision the module reports on rather than makes.

#### Generating: a print document, not a rendered file

`ACTION_GENERATE`, `admin_post_` only (no `nopriv`). Logged in; `$record` resolved as section 5.5 (a member's own institution overrides any posted value; a manager's switcher is honoured); nonce keyed to the institution; `decide( ACT_AGREEMENT, subject_institution( $record ) )`; ceiling 10 generations a day per institution by `add_option()` bucket; name (editable, pre-filled from the trimmed Airtable `Name`, capped at 200, `sanitize_text_field()`; an empty name refuses generation naming the program manager) and language cleaned; `merge()`; the `generated` post inserted with `name_on_document`, version, language and actor; Airtable per T2 (failure stamped pending); then a **standalone HTML document** is echoed: no theme chrome, the agreement's own print stylesheet inlined (A4 via `@page { size: A4; margin: 20mm }`, 11pt body, signature blocks as two columns with three underlined lines each), a footer repeated on every page through the document's table footer group (a fixed-position element repeats but is out of the flow, so the text is laid out as though it were not there and prints under it; a footer group's height is reserved on every sheet before the rows are broken), reading *WordPress Credits Program - Collaboration Agreement - template 2025-11-04 (en) - generated for <name> on <date>*, the document `<title>` set to `Collaboration-Agreement-WordPress-Credits-<sanitize_title( name )>` so the browser proposes it as the PDF name, and a registered `agreement-print.js` calling `window.print()` once loaded. The institution's Save as PDF produces the PDF decision 7 asks for; the content is deterministic from (template version, language, name), the footer carries the version the reviewer compares, and the same document is what "Regenerate the template as they saw it" reproduces from the `generated` post.

This replaces the agreement note's vendored tFPDF, `WPCPM_Pdf` and DejaVu Sans, and their risk rows (font-cache writes on the generate path, refusal of non-Latin names). A page count is not available without a renderer and is not needed: the footer, not the page number, is what the reviewer reads. If the product owner later wants a server-rendered file with a program logo, that is a renderer for the agreement alone, and nothing else in this design moves.

#### Upload: the plugin's first multipart handler

The form is on the panel and on the manager's institution row (a manager uploads on behalf, because the two bespoke agreements and many legacy copies arrive by email). `enctype="multipart/form-data"`, `data-wpcpm-once`, fields: `wpcpm_agreement_file` (`accept="application/pdf,.pdf"`, a convenience; the server decides), `wpcpm_agreement_kind` (radios `template` / `own`, server-held allowlist), `wpcpm_agreement_signed` (must be `1`, absent and `0` and anything else refuse, hidden `value="0"` companion rendered), `wpcpm_agreement_record` (ignored for a member, honoured for a manager), nonce keyed to the institution. No free-text field: a textarea on an upload form is a second place for personal data to land.

```
handle_upload():
   1. logged in, else wp_die( 403 )
   2. resolve $record; check_admin_referer( ACTION_UPLOAD . '_' . $record )
   3. decide( ACT_AGREEMENT, subject_institution( $record ) )
   4. ceiling agreement_uploads_per_day (5) per institution; over -> flash 'agreement-busy', nothing stored
   5. declaration "1"; kind in the allowlist; no `submitted` post already exists (one in review at a time)
   6. $_FILES: UPLOAD_ERR_OK; size > 0 and <= agreement_max_mb * MB; is_uploaded_file()
   7. wp_check_filetype_and_ext( $tmp, $name, array( 'pdf' => 'application/pdf' ) ): ext 'pdf', type 'application/pdf'
   8. the first five bytes are "%PDF-"; finfo_file() says application/pdf. Both: the first is cheap, the second is what a renamed .exe fails
   9. the courtesy scan: decode #xx name escapes and inflate every stream whose dictionary names /FlateDecode, then look for /Encrypt, /Launch, /JavaScript, /JS, /OpenAction, /AA, /EmbeddedFile.
      /Encrypt and /Launch refuse with a named message. The rest are stored in _wpcpm_agr_flags and shown to the reviewer.
  10. WPCPM_Private_Files::store( $tmp, 'agreements' ) -> relative path, size, sha256
  11. wp_insert_post(): private, post_author the uploader, meta per section 9; a `generated` post for this institution becomes `superseded`
  12. Airtable per T3; WP_Error -> _wpcpm_agr_airtable_pending = 1, the sync retries
  13. rewrite wpcpm_agreement_<record>; mail; flash; bounce to the panel
```

Order 4 before 6: the ceiling is decided before ten megabytes are inspected. Order 7 to 9 before 10: nothing reaches the private directory that has not passed every check. **The scan is a courtesy and is presented as one**: the panel, the checklist and the tests say the reviewer's protection is download-never-inline plus their own viewer, never that the scan is evidence, because a token can be hidden in ways a bounded scan will not find. The original filename is stored escaped for display only and is never used on disk or in a header.

#### Where the file lives: `WPCPM_Private_Files`

`includes/class-wpcpm-private-files.php`. Base `wp_upload_dir()['basedir'] . '/.wpcpm-private/'` (the leading dot is load-bearing: see open question 10), `wp_mkdir_p()`, `index.php`, an `.htaccess` with `Require all denied` and `Deny from all` for hosts that read one. Path `<YYYY>/<32 hex from random_bytes(16)>.pdf`, mode 0640, **written encrypted**: `store( $bytes, 'pdf' )` returns the relative path, the sha256 of the plaintext and its size, and `read( $relative )` is the only reader. `forget()` deletes. **The plugin never prints the file's URL anywhere**; the only route to the bytes is `handle_download()`, which reads through `read()`. `path( $relative )` resolves through `realpath()` and refuses anything outside the base. A site upgrading from 1.67.0 has its old undotted directory emptied into the new one by `ensure()`.

**The direct-access probe.** Atomic serves uploads through nginx, which ignores `.htaccess`. `probe()` writes `probe-<random>.txt`, requests its public URL with `wp_remote_head()`, records status and time in `wpcpm_private_probe`, deletes the file, and the storage card reads either "the host blocks direct requests to the private directory" or, in a warning, "the host serves files in the private directory directly; names are unguessable, but ask the host to block `/wp-content/uploads/wpcpm-private/`". Run at activation and by a button. Open question 10 asks the host.

#### Serving it

`ACTION_DOWNLOAD`, a `wp_nonce_url()` link keyed to the post ID: not logged in redirects to login with a return; nonce; the post exists, is ours, has a file (a legacy row has none: 404); `decide( ACT_AGREEMENT, subject_post( $post, '_wpcpm_agr_institution' ) )`, so a member downloads their own institution's uploads in any state and another institution's is the one refusal; `nocache_headers()`; then `Content-Type: application/pdf`, **`Content-Disposition: attachment; filename="<sanitize_file_name( institution slug + date )>.pdf"`** built from a server-chosen name and never from `_wpcpm_agr_original_name`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'; sandbox`, `Cache-Control: private, no-store`, `Content-Length`; `readfile()`; exit. Never inline, for the reviewer as much as anyone: a PDF has a scripting model, and a reviewer's browser should hand it to a viewer of their choosing.

#### Accept, return, revoke, reinstate, withdraw

`handle_accept()`: capability then nonce; the post `submitted`; `add_option( 'wpcpm_agreement_lock_' . $record )`; live `get_record()` on Institutions (a `WP_Error` refuses: an acceptance that cannot read the record must not write it; a terminal stage refuses by name); the Airtable PATCH per T5 in one `update_records()` call, `Current Stage` = `Confirmed` only when the stage read precedes it in `STAGE_ORDER` (empty counts as preceding); then the previous `accepted` post to `superseded`, this post to `accepted`, `_wpcpm_agr_decided_by` / `_at`, event row; the option rewritten (the gate is open from this line); mail; release.

The confirm names everything that leaves the building: *Accept the signed agreement from Universidad Example? This opens their account on the site, sets Current Stage to Confirmed in Airtable, and emails the 3 people at the institution. You can revoke it from here later.* The reviewer checklist sits above the button and is read, not ticked. Template kind: the footer on the signed copy names template version <current>; the name on the document is <name_on_document> (Airtable: <Name>); the institution's signature block is filled. Own kind: read the whole document; it names the WordPress Foundation and the institution; it does not commit the program to anything the template does not. Both: the flags line, with the sentence that the scan is a courtesy.

`handle_return()`: note required, 20 to 2000 characters, `sanitize_textarea_field()`; Airtable per T6; post `returned`; the note mailed verbatim with reply-to the manager. `handle_revoke()`: note required; Airtable first per T8; post `revoked`; **the option deleted**, so the gate closes on this request; mail. Dialog: *Revoke the accepted agreement for Universidad Example? Their account locks immediately, Airtable's Agreement Status becomes Revoked, and your note is emailed to the 3 people at the institution. Current Stage is not changed; change it yourself if the partnership has ended.* `handle_reinstate()`: Airtable first per T9; the post back to `accepted`; the option rewritten; the abort that makes revoke a safe click. `handle_withdraw()`: a member or a manager on a `submitted` post, subject from `_wpcpm_agr_institution`, the file deleted at once, no mail.

#### "Agreement on file", by hand, with a link

`ACTION_ON_FILE` on the manager's institution row: the Drive link (required; `https`, host `drive.google.com` or `docs.google.com`, anything else refused with "paste the Drive link to the folder or the file"), the date signed (optional), a one-line location note ("second folder, the 2025 copy"). Airtable per T7 first, then the legacy post, then the option. No mail.

A manager may equally set the same columns in the base's grid view, which for 42 rows is the faster tool, and press Refresh; T12 materialises the legacy post and the row settles identically. The screen says which route produced each row.

**Per-row and bulk provisioning of a Confirmed institution are both gated on this state.** The bulk button counts Confirmed institutions with no recorded agreement and refuses while the count is above zero, naming them; the per-row control refuses the same way, naming the missing Drive link. Decision 24 is the legal basis; recording it per institution is what turns the basis into something the site can act on, and what stops a partner that signed years ago being emailed that its first step is to sign.

#### Notifications

`WPCPM_Institutions::notify_managers( $context, $build )` is the one mechanism, shipped as a shell in Phase 1 (used by the last-member notice) and fed by every queue event after: recipients are the `agreement_notify` setting when non-empty, otherwise every account holding `CAP_MANAGE` with an email, through `WPCPM_Mail::send()` and `send_to()` so every send is logged. Program managers here are WordPress administrators, so the default reaches technical ones too; the setting narrows it and should be set before the first real upload. The country contact is named in the body, never added as a recipient.

- `agreement-landed`: one per upload. Subject `[site] Signed agreement from <institution> is waiting for review`.
- `agreement-reminder`: a daily digest from `CRON_REMINDERS = 'wpcpm_agreement_reminders'`, sent only when at least one item has waited longer than `agreement_review_days`, at most once a day. A queue that is a person's job needs the reminder more than the first notice.

| Context | When | To | Says |
| --- | --- | --- | --- |
| `agreement-received` | T3, T10 | every member | received on <date>, uploaded by <name>; expect an email either way, usually within <days> working days; withdraw if it was the wrong file |
| `agreement-accepted` | T5, T9 | every member | accepted on <date>; the account is open; three lines on what you can do now; download link |
| `agreement-returned` | T6 | every member | the note verbatim, the manager's name, upload again; reply-to the manager |
| `agreement-revoked` | T8 | every member | the note verbatim; the account is limited to the agreement panel; the country contact's address |

Every member is mailed at every step, because equal members should not learn from a colleague that the account is open. `members_of()` is the set.

### 7.5 Unlocked: the dashboard and the people on it

`WPCPM_Institutions_Dashboard`, page slug `institution-dashboard` (chosen once, never renamed; the title is the product owner's and `TITLE_VERSION` is an integer from day one), gated to `ROLE_INSTITUTION` via `_wpcpm_access_level` only when not already set, `institution.css` depending on `WPCPM_Mentors_Dashboard::STYLE`. `WPCPM_Dashboards::links()` gains a third entry; `WPCPM_Notices::applies_to()`'s `institution` case delegates to `is_member()`. A mentor who is also a member holds both dashboards; where `login_redirect` lands them is open question 12.

Layout, for a settled institution: identity header from the pipeline index, falling back to the `wpcpm_institution_profile` stamp only for an institution the index has not read yet; the manager switcher under `CAP_MANAGE`; the cohort picker and the comparison strip (7.7); the filter bar (status, search, cohort, all GET arguments narrowing before render, so a filtered roster is a URL a colleague can be sent); the four roster groups; the "Not yet in the Students table" list; the People card; the agreement card; the footer with the index's read time.

**The four groups**, built by `WPCPM_Roster_Index::groups()` from index rows, filtered by cohort first:

| Group | Rows | Note |
| --- | --- | --- |
| Current | status in `tracked_statuses()['active']` and `reports` non-empty | the account-bearing students |
| Waiting for a mentor | status in `['active']` and `reports` empty | imported and legacy rows alike, with `has_mentor` so "a mentor is assigned but no report record exists" reads differently from "no mentor yet" |
| Finished | status in `['past']` | collapsed |
| Did not start | `Not moving forward`, `Fail`, empty | counted, collapsed, named honestly: the applicants who never began, which is the question institutions ask first |

`SPAM` and `Duplicated` rows are never rendered to an institution as a person. A fifth list, "Not yet in the Students table", holds account-bearing students whose `institution_source` is `reports`, read from user meta, read-only, with a line saying a program manager needs to complete the record. Nothing is silently dropped.

Columns from the index and cached meta: student, program (`WPCPM_Program::label()` plus badge, `wpcpm-badge--paused` and `--pending` for the two states), dates, days left, mentor name, WordPress.org, team, website, field of study, tutor, hours (from Phase 5; "n h" for the Developer Track, never "n of 0"). **`accessibility` is absent** from every column, detail row and export, asserted: it was disclosed to the program, not to the school. Rows in the "No start date" bucket render with a "Set start date" link opening the edit form.

One student's detail view at `?wpcpm_institution_student=<user id>`, `decide( ACT_VIEW_STUDENT, subject_student_account() )`, a stranger's ID falling back to the roster: identity and program table (every row printed even when empty), the Student Report Card read-only through `render_body( $student, $program, true )` fetched lazily and authorised through `claim( $record, ACT_VIEW_REPORT, 'report' )` in `WPCPM_Student_Report_Form::rest_permission()`'s new institution branch, the mentor's name and email and nothing else, and the note stream. Course grades appear only here and in the single-student export (open question 5 of the first spec, kept).

**The People card** lists every live member with `how` and `since`, the member whose address equals Airtable's `Contact Email` marked as the program's contact for information and nothing attached to it, and a Remove control per row (Leave on the viewer's own). Invite ships in Phase 4 (section 12); until then a manager adds accounts. `handle_remove_member()`: nonce keyed to the subject user; **the subject's institution read from the subject's own stamp**; `decide( ACT_MANAGE_MEMBERS, subject_institution( $that ) )`; `detach()`. The confirm: *Remove Anna Kowalska's access to Universidad Example's students on this site? She will be emailed that her access was removed, and so will the other 2 members. Her account is kept.* Equal power means one member can remove every other; the mitigation is that it cannot be done quietly, and that a manager re-adds in one click from `former_members_of()`. A member removing themselves as the last member is allowed, with the warning that nobody will be left and the program will be told.

**The manager backstop**, on the Institutions screen per institution: live members, pending invitations, former members, whether `Contact Email` is a member; Add account (name and email; refuses any existing account except a former member of this institution or a mentor, because a hand-made account is somebody's to explain), Re-add, Remove. Counts: institutions with no live member (the one that pages a manager), contacts who are not members, invitations older than 7 days.

### 7.6 Import: `WPCPM_Institution_Import`

`includes/modules/class-wpcpm-institution-import.php`, rendered inside the institution page, behind `import_enabled` (off by default, like `applications_enabled`).

```
const ACTION_CHECK    = 'wpcpm_import_check';     // parse, clean, duplicate-check, stage; writes nothing to Airtable
const ACTION_CONFIRM  = 'wpcpm_import_confirm';   // create the clean rows of one staged batch
const ACTION_CONTINUE = 'wpcpm_import_continue';  // the next slice
const ACTION_CANCEL   = 'wpcpm_import_cancel';
const CRON_TICK       = 'wpcpm_import_tick';
const POST_TYPE       = 'wpcpm_import_batch';
const MAX_ROWS = 300;  const MAX_BYTES = 262144;  const CHUNK = 50;  const BUDGET = 12;  const LOCK_TIMEOUT = 120;
const CHECKS_PER_HOUR = 5;  const ROWS_PER_DAY = 600;
```

**Every handler, in this order**: `decide( ACT_ADD_STUDENT, subject_institution( $institution ) )` where `$institution` is `resolve_institution()`'s answer (a member's own stamp; a manager's switcher), refused outright when `''`; `check_admin_referer()`, keyed to the batch for confirm, continue and cancel (the subject read from `_wpcpm_batch_institution`, never the form); only then anything that reaches the network. The tests assert the handler reads no institution ID from the request, and that no `create_records()` call ever carries an empty or absent `Educational Institutions` cell, which is how a manager import with `institution_of() === ''` would otherwise create a silent orphan per row.

**The form.** Batch-wide first, because decision 9 makes them properties of the batch: Program (`<select>` of `WPCPM_Program::labels()`, value the Airtable status, server-held map); Start date (`type="date"`, required, pattern and `checkdate()`, within 365 days either side of today: future dates are normal, ten years out is a typo); End date (optional, after the start and at most 365 days after it); Confirmation (must be true: *These students have been told that their name, email address and WordPress.org profile are shared with the WordPress Foundation for the WordPress Credits Program*, recorded on the batch and never written to a student's `Privacy Policy Compliance`, which is the person's own act). Then one student (name, email, profile, field of study, tutor) or a file (`accept=".csv,text/csv"`) plus a paste textarea. Both routes produce the same row list.

**The file is never stored.** Read from `$_FILES` after `is_uploaded_file()`, refused over `MAX_BYTES`, `file_get_contents()`, never `wp_handle_upload()`, which would move a CSV of names into the web-served uploads directory. Parsing, each step tested: refuse invalid UTF-8 rather than strip it (a Latin-1 export is the common case and mangling "Fidélitas" is worse than refusing); strip a BOM; detect `,` or `;` from the header (Excel in most of the program's countries exports semicolons); `fgetcsv()` over `php://temp` so a quoted newline survives; normalise headers and resolve aliases (`name` / `full_name` / `student`; `email` / `e_mail` / `mail`; `profile` / `wp_profile` / `username` / `wporg`; `field_of_study` / `study`; `tutor`); unknown columns listed, not refused; a `start_date` or `program` column, if present, must be empty or equal to the batch value on every row, else the file is refused naming the rows; drop empty rows; over `MAX_ROWS` refuses with the count.

**Cleaning**, through `WPCPM_Field_Value` (hoisted out of the report form and the feedback module in Phase 0) plus this module's rules: name `sanitize_text_field()`, whitespace collapsed, 2 to 200 characters, **refused when it starts with `=`, `+`, `-` or `@` or contains a tab, carriage return or pipe**, because a name is exported to a spreadsheet by every program manager and lands in the subject line of the welcome automation, and no real name starts with those; email `sanitize_email()` then `is_email()` else the row is refused, stored lowercased and trimmed (`email_key`), MX absent a soft warning; profile through `wporg_username()`, stored as the canonical `https://profiles.wordpress.org/<handle>/`; field of study matched case-insensitively against the nine pinned choices, an unknown value rejecting the field and never the row (typecast is off); tutor 120 characters with the same first-character rule.

**Duplicate detection**, all before any write, re-run at confirm:

1. Inside the file, by `email_key` and by handle: both rows blocked as "duplicate of row N in this file".
2. Students by email: one `fetch_all()` per chunk of 50 with `formula_in( 'Email', $chunk, true )`, the new third argument wrapping the field in `LOWER()`.
3. Students Reports by email, the same.
4. Profile, both tables: `OR(FIND('<handle>', LOWER({WordPress Profile})) > 0, …)` per chunk for handles of three characters or more, every returned value normalised and compared for exact equality in PHP, so a URL variant cannot defeat it and "ann" inside "joanna" cannot trip it.
5. The site: `get_user_by( 'email' )`.
6. Near-name inside this institution, on the index.

**Verdicts.** The institution sees exactly three kinds of answer about the outside world, and none of them says where.

| Verdict | Trigger | Shown as | Effect |
| --- | --- | --- | --- |
| `invalid` | cleaning | the named problem | not created |
| `duplicate-file` | step 1 | "duplicate of row N" | neither row created |
| `exists-here` | a hit linked to this institution on either table | "Already on your roster: name, status, cohort" linked to the roster row; for a reports-only row "this student has a program record but no enrolment record; a program manager needs to complete it" | hard block |
| `blocked` | **any other hit** in steps 2 to 5: another institution, an unlinked Students row, a reports row with no institution, a site account of any kind | "This student cannot be imported from here. Ask a program manager." | hard block; the reason is stored on the batch row for the manager and **never shown to the institution** |
| `near-name` | step 6 | "Similar name on your roster" | soft |
| `ok` | | | created |

The `exists-elsewhere`, `exists-unlinked` and `account-exists` verdicts of the import note are gone: an import preview that answered each row differently over the whole base was a membership oracle in bulk, the very thing the fence's single refusal exists to prevent. Adoption of an unlinked row is a manager's act from the reconciliation card, which refuses when the row carries a mentor and an automation-matching status or when any Students Reports row exists for the address, because writing the link onto such a row fires the creation automation and produces a second reports row.

**Ceilings.** `ACTION_CHECK` claims an `add_option()` bucket per institution: `CHECKS_PER_HOUR` and `ROWS_PER_DAY`, refusing over either with a named flash before a single row is parsed. One staged batch per institution at a time (a second check replaces it after cancel). Every check writes one line to a capped `wpcpm_import_log` option: institution, member, rows, blocked count; batches whose blocked ratio exceeds a half are listed on the manager reconciliation card, because a member probing addresses looks exactly like that.

**Preview and confirm.** `ACTION_CHECK` stores the cleaned rows, verdicts, batch values, confirmation tick and unknown columns on a `wpcpm_import_batch` post (`private`, `post_author` the member, `_wpcpm_batch_institution`, `_wpcpm_batch_state` `staged`, `_wpcpm_batch_rows` with per-row state), and redirects to `?wpcpm_batch=<id>`. Any member of the institution can see, confirm or cancel it (`decide()` on `subject_post()`); the audit names whoever pressed. The confirm button and its `onsubmit` say: *Create 18 student records for <institution> on the WordPress Credits Program 150h, starting 7 September 2026. No email is sent now. Each student is emailed by the program once a mentor is assigned, and receives their login when their account is created. Do not send these students to the registration form; it would create a second record.* The nonce is keyed to the batch ID and `md5()` of the canonical payload plus the institution.

**Creating, and what makes it safe to retry.** `create_slice( $batch_id )`, shared by confirm, continue and `CRON_TICK`:

1. **Before every slice, whoever runs it**: `is_settled( $institution )` and the confirming member still a live member of `_wpcpm_batch_institution` (a manager-confirmed batch checks the manager still holds `CAP_MANAGE`). Either failing parks the batch as `blocked` with the reason and names it in the summary. A cron continuation has no acting user, and "enforced server-side in every handler" must include the continuation, or a revoke during a 300-row batch stops nothing.
2. `add_option( 'wpcpm_import_lock_' . $institution )` with stale release after `LOCK_TIMEOUT`.
3. `staged` becomes `creating`; steps 2 to 4 of the ladder re-run for `pending` rows; a changed verdict blocks the row and is named.
4. For each `pending` row until `BUDGET` seconds: a row in state `creating` with no record ID searches `{Site import key} = '<key>'`, then email, before any create; state `creating` saved **before** the request; `create_records()` one row per call (a batch call returns a re-indexed list and mis-assigns IDs after the first dropped row, and the wrong ID stamped is the wrong person fenced); a `WP_Error` marks the row `failed` with the message verbatim and the loop continues; the ID stamped and saved immediately; `WPCPM_Roster_Index::insert()` so the roster shows the student now; a `wpcpm_inst_request` of kind `add`.
5. Rows remaining: schedule `CRON_TICK` in 30 seconds and show progress with Continue. Otherwise `done`, one `wpcpm_audit_entry` for the batch, a summary flash naming created, blocked and failed.

`Site import key` is `imp-<batch id>-<row index>`, the one new Students column, because email alone cannot tell "created a second ago, response lost" from "somebody else enrolled this student". Cancel deletes a `staged` batch; a `creating` or `done` batch cannot be cancelled and the screen says why. Batch posts are purged by `wpcpm_purge_applications` 30 days after `done` or 7 days after `staged`.

**What is written**, to the Students table only, typecast off:

```php
array(
    'Full Name'                => $row['name'],
    'Email'                    => $row['email'],                 // lowercased
    'Status'                   => $batch['status'],              // WPCPM_Program::STATUS_150H | STATUS_50H | STATUS_DEV
    'Educational Institutions' => array( $institution ),         // resolve_institution(), never posted, never empty
    'Start Date'               => $batch['start'],
    'End Date'                 => $batch['end'],                 // only when given
    'WP Profile'               => $row['profile'],               // only when given
    'Your field of study'      => $row['field_of_study'],        // only when given
    'Tutor '                   => $row['tutor'],                 // only when given; the trailing space is the column's name
    'Site import key'          => 'imp-' . $batch_id . '-' . $index,
)
```

Not written, each with a comment beside the map: `Mentor` (an assignment, not a request), both consent checkboxes (the student's own acts), `Notes`, `HelpScout`, `Total hours`, `Students Reports`, `Accessibility needs`, `Languages`, `Prefered language`, `Contribution Area`, `Lessons`, `Feedback`, `Tutor email`, `Tutors official`. No Students Reports row and no Feedback row: the automation creates both when a mentor is assigned.

**What provisioning must refuse.** `provision_student()` today adopts any account found by email that carries no student stamp. The import hands the institution the choice of that email, and a mentor assignment is routine manager work, so an institution importing a mentor's or another member's address would, after the next sync, turn that account into a student on its roster. So `provision_student()` gains the rule the first spec set for institution accounts: **an account found by email that carries any `wpcpm_*_record_id` (live or `_was`) or any role other than Student or Subscriber is a conflict, not a match**, counted on the reconciliation card. Real students are protected by their own stamp; this protects everyone else.

**How a created student flows.** Now: a Students row with no mentor, an index row, "Waiting for a mentor", an open `add` request with its age on the manager queue; no email from anyone. A manager assigns a mentor in Airtable: the automation creates the reports and feedback rows and the welcome automation mails the student. The next students sync: the reports row is paged, joined by email, `provision_student()` creates the account through the one provisioning path, the index row gains its reports ID and user ID, the `add` request closes. The invitation: with `send_welcome_email` on, queued; with it off (the default), sent by a manager or by the institution's invite control with its 24-hour and 25-per-day rules and `queue_invite()` beyond the cap. Who reads the `add` queue is open question 3.

### 7.7 Track by cohort: `WPCPM_Cohort`

`includes/class-wpcpm-cohort.php`, all static, no state, beside `WPCPM_Program`.

```php
class WPCPM_Cohort {

    const NONE = 'none';   // the bucket for rows with no usable start date; a first-class key in every picker and count

    /** Statuses that are not a person who signed up. `Interested` is a lead, not an enrolment. */
    const NOT_SIGNED_UP = array( 'SPAM', 'Duplicated', 'Interested' );

    /**
     * The semester containing a start date, as `YYYY-H1` or `YYYY-H2`, or NONE.
     *
     * H1 is 1 January to 30 June, H2 is 1 July to 31 December. Read from the string, never
     * through a timestamp: strtotime() on a date-only string lands at midnight in whatever
     * timezone PHP has, and a student who started on 1 July could be filed under June on a
     * site set west of UTC. Pattern AND checkdate(), so 2026-02-31 is NONE rather than March,
     * and a datetime is NONE so a field type change surfaces as an empty cohort in the tests.
     * Verified against the base on 2 September 2026: both boundaries fall between intakes.
     */
    public static function key( $date );
    public static function is_key( $value );        // /^\d{4}-H[12]$/ or NONE, for request arguments
    public static function previous( $key );        // 2026-H1 -> 2025-H2; NONE -> ''
    public static function current();               // key( wp_date( 'Y-m-d' ) )
    public static function label( $key );           // 'January to June 2026', 'July to December 2026', 'No start date'. Never a season word.
    public static function range( $key );           // array( 'from', 'to' )
    public static function compare( $a, $b );       // NONE sorts last

    /**
     * Participation counts for one cohort over index rows. The seven buckets sum to signed_up
     * by construction, and the two-number comparison decision 11 allows is signed_up and
     * graduated read off this array. Nothing deeper lives here, deliberately.
     *
     * @return array{signed_up, graduated, pending, active, withdrawn, not_started, other}
     */
    public static function participation( array $rows, $key );
}
```

| Bucket | Rule |
| --- | --- |
| `signed_up` | every row whose key matches and whose status is not in `NOT_SIGNED_UP` |
| `graduated` | `Status` = `Graduate`, exactly, not `is_graduate()`'s loose regex |
| `pending` | `Pending graduation`, printed separately until open question 1 is answered |
| `active` | `WPCPM_Program::is_track()` or `Paused` |
| `withdrawn` | `Dropped out`, `Fail` |
| `not_started` | `Not moving forward` |
| `other` | anything else, so a new status in the base shows up as a number rather than vanishing |

**The picker** is a GET argument `wpcpm_cohort` validated with `is_key()`, built from the institution's index rows: distinct keys with `signed_up`, newest first, then "No start date (n)" only when n is above zero. **The comparison strip**: this cohort (default `current()`, falling back to the newest the institution has) against `previous()`, two numbers each; when the previous calendar semester holds no rows it says "No students started in July to December 2025" rather than zeros that look like a failure. The strip, the table and the semester report all print the index's read time, so a stale count never looks fresh. The manager screen carries the program-wide "no start date" count split by status, because the 4 Not moving forward rows are nobody's problem and the 1 Developer Track row is somebody's.

### 7.8 Edit: the allowlist and the audit

`WPCPM_Institution_Student_Form::fields()`, keyed by table then exact column name, form keys table-scoped (`key( $table, $name )` hashing `$table . '|' . $name`, because both tables carry `Email`):

```php
'reports'  => array( 'Name' => array( 'type' => 'text', 'maxlength' => 200 ) ),
'students' => array(
    'Full Name'           => array( 'type' => 'text',   'maxlength' => 200 ),
    'Start Date'          => array( 'type' => 'date' ),
    'End Date'            => array( 'type' => 'date', 'after' => 'Start Date', 'max_days' => 365 ),
    'Your field of study' => array( 'type' => 'select', 'choices' => 'field_of_study' ),
),
```

Deliberately absent, each with a comment beside the map: `Status` on either table; `Hours` and `Total hours` (an institution that could set hours could grant credit for work that did not happen); both institution links (the fence's anchor); `Mentor`; `Privacy Policy Compliance`; every grade column and `Post Reflection:` URL; the formula link fields; `Notes`; and **`Email` on both tables**, because on Students Reports it is the mirror automation's join key and `provision_student()` never rewrites `user_email`, so an institution "changing" it would leave the account signing in at the old address forever (open question 6).

`WPCPM_Field_Value::clean()` gains `date` (empty is `null`, which clears; otherwise pattern and `checkdate()`); `select` (membership in a server-held list, never a pass-through) has been there since the Phase 0 hoist. Cross-field rules run over the accepted cells with the record's current values underneath; `max_days` refuses 2036.

`handle_save()`: nonce first (keyed to the subject; `claim()` makes an HTTP request and a cross-site POST must not cause one), `claim( $subject, ACT_EDIT_STUDENT )`, `$before` from the claim, the walk, the Students row resolved and proven (`students_row_for()`: the report's `Students` link, else `LOWER({Email})`, two-plus matches refusing the Students half with "a program manager needs to merge them first"), `scope()`, write Students only for the mirrored fields and Students Reports only for `Name`, one audit row, three cache invalidations (`WPCPM_Student_Report_Form::forget()`, `apply_report()` widened to `report_name` / `report_start` / `report_end`, a new `apply_student_row()`), and `WPCPM_Roster_Index::update()` so the roster shows the change now. Extending is `End Date` through the same handler, logged as `extend` when it is the sole change.

Notes: `WPCPM_Mentor_Notes` gains `_wpcpm_note_audience` (absent meaning `mentor`), `get_notes()` takes the audience as a required argument with no default, and `user_can_read_note()` reads the note's own meta. Institution notes ship with the write handler, never as an empty panel.

### 7.9 Report: `WPCPM_Semester_Report`

`includes/modules/class-wpcpm-semester-report.php`. The report is a **snapshot document** generated on request, edited on the dashboard, exported by printing. Two rules shape it: participation comes from the index (never a live roster read, so the 171 Not moving forward rows the accounts side never pages are counted), and **the snapshot contains nothing the institution could not print**: no email, no status beside a name, no hours, no grade, no accessibility disclosure. A test walks `_wpcpm_report_data` and asserts no key is `email`, `email_key`, `status`, `accessibility`, `hours` or `grade` and no string value passes `is_email()`.

| # | Section | Generated from | Narrative |
| --- | --- | --- | --- |
| 1 | Program Overview | nothing | yes, default supplied |
| 2 | Participation | `participation()` over the index rows for the cohort, plus the previous cohort's two numbers | optional sentence |
| 3 | Contribution Teams | distinct `Main Contribution Team` across the cohort's joined Students Reports rows, with counts | optional |
| 4 | Student Projects & Blogs | per consenting student: name or blog host per their consent, `Personal Website URL`, each non-empty `Post Reflection:` and `Closing post URL` labelled from `WPCPM_Student_Report_Form::fields()` | intro, default supplied |
| 5 | Recognition and events | per consenting student, `WP event participation URL`, identical URLs grouped with a count | yes |
| 6 | Continuing Engagement | nothing | yes |
| 7 | Student Feedback | quotes released by their authors, with translations the institution adds | intro, default supplied |
| 8 | Looking Ahead | nothing | yes, default supplied |

Not generated, each with its reason in the docblock: hours and targets (decision 23, and the credit-bearing document is a different document), grades, any feedback rating even aggregated (an aggregate over ten people is a disclosure about ten people), `Contribution Project Summary`, mentor names.

**The reads.** `generate()` takes the cohort's rows from the index, then reads Students Reports and Feedback **by email in chunks of 50** through `formula_in( 'Email', $chunk, true )`, never by an institution-name formula (Airtable's `LOWER()` is Unicode-aware and PHP's `strtolower()` is not, so a name with `Ł` would fetch nothing). A reports row is kept when its institution link is empty or contains this institution and dropped when it names another; a Feedback row likewise on `Institution`. Joined on `email_key`. When one student matches several reports rows (23 today), the row whose normalised `Name` matches wins; none or several means no links and one `ambiguous` in the withheld line. Fields are never unioned across rows. Each read is cached 300 seconds per institution; any `WP_Error` aborts the whole generation with the message verbatim, because a report with Participation and no Projects looks finished. `ACTION_GENERATE` carries an `add_option()` lock keyed to institution and cohort and `data-wpcpm-once`.

**Consent.** A student's name and links appear only if they said so; their feedback is quoted only if they said so; the school sees a candidate quote only **after** the student released it. That keeps the promise in `13-student-feedback.md`, which changes in the same commit to name the exception. Two select fields join the Finishing-up form's Permissions box (`group => 'permissions'`), written to Airtable through the existing `handle_save()`:

- `F3 - Report: my institution may list me in its semester report`: `Yes, with my name`, `Yes, by my blog address only`, `No`.
- `F3 - Report: my institution may quote my feedback in its semester report`: `Yes, with my name`, `Yes, without my name`, `No`.

`WPCPM_Student_Feedback::handle_save()` changes in two ways. **For any field with `group => permissions` it requires `get_current_user_id() === $student_id`**, drops the cell with a named flash otherwise, and renders those fields read-only when `$can` came from `CAP_MANAGE`: `user_can_edit()` includes managers, and a manager setting "Yes, with my name" on a student's behalf is the one write this module must never make. On the self path it stamps `wpcpm_report_permissions` (answers, wording, time) and **deletes the institution's Feedback transient**, so a withdrawal is visible on the next render. `record_for()` writes `Institution` from `wpcpm_student_institution` on create and, among the 50 duplicated Feedback addresses, prefers the row linked to the student's institution, because a consent on a row linked elsewhere is never fetched and the student is withheld although they said yes.

**Consent is re-checked on every render, screen and print alike, through one function**, against the Feedback rows; a withdrawn quote or name is dropped from the drawing and the page says "one quote was withdrawn since this draft was generated". A consent-only regeneration (`ACTION_REFRESH_CONSENT`) rewrites the snapshot's `students[]` and `quotes[]` and drops the translation of any quote whose id is gone, and it is allowed on a `final` report, because a withdrawal must be able to reach a stored document. The original text of a quote is never editable in the handler: `ACTION_SAVE` reads translations and flags for quote ids in the snapshot and ignores everything else.

`ACTION_ASK` (`report-consent` mail): one message per student in the cohort with a candidate answer and no permission answer, through `WPCPM_Mail::send()`, never twice inside 30 days, stopping at 25 per press with the rest queued. Whether institutions may press it is open question 2.

**Storage**: `wpcpm_inst_report`, one post per institution and cohort, `private`, `supports` title, editor, author, revisions; `_wpcpm_report_institution` (the policy's input), `_wpcpm_report_cohort`, `_wpcpm_report_data` (the snapshot, `'v' => 1`), `_wpcpm_report_sections` and `_wpcpm_report_choices` registered with `revisions_enabled` (WordPress 6.4, inside the plugin's 6.5 floor; the test asserts a restore brings back title, sections and choices together, with serialising the choices into `post_content` as the fallback), `_wpcpm_report_generated`, `_wpcpm_report_state` (`draft` | `final`), `_wpcpm_report_exported`. `post_content` holds a plain-text rendering of the narrative so the revision diff screen shows words.

**Editing** at `?wpcpm_report=2026-H1` on the dashboard page, never wp-admin: each section as a card, the generated part read-only above a `<textarea>` (`MAX_TEXT` 5000), a hide toggle, the quote picker with include, translation and show-name (only when `named_allowed`). One form, `ACTION_SAVE`, `decide( ACT_EDIT_SEMESTER_REPORT, subject_post() )`. A hidden `post_modified_gmt` refuses a stale save with "someone at your institution saved this report after you opened it" and stashes the text: decision 13 puts several equal users on one institution. `ACTION_RESTORE` on a revision (its `post_parent` checked first), `ACTION_FINAL` and `ACTION_REOPEN`.

**Output.** Every link prints its URL as visible text, on screen and in print, as the reference does. `ACTION_PRINT` is an `admin_post_` GET handler (capability and membership through `decide()`, then nonce) echoing a standalone document with `report-print.css` inlined, no theme chrome, the report title as `<title>`, `@page { margin: 18mm }`, `page-break-inside: avoid` on each quote and student row, a page break before Student Feedback, and `report-print.js` calling `window.print()`. Quotes render the original in a `<blockquote>` then "English:" with the institution's translation; no machine translation. The same `render_document( $post, $for_print )` partial draws both.

The manager screen gains a "Semester reports" card: every report, institution, cohort, state, generated and last edited, opened through the switcher.

### 7.10 Graduate and withdraw

`WPCPM_Institution_Students::states()` returns `graduated => 'Graduate'` and `withdrawn => 'Dropped out'`, written to Students only, through `claim( $record, ACT_CHANGE_STATUS )`.

**Guard 1**: only these two. `Paused` and `Pending graduation` are tracked now (decision 21), so the first spec's reason (a student moved to either lost their role at the next sync) no longer holds and its paragraph is rewritten: the two are not offered because pausing and pending graduation are the program's calls, not the institution's. The assertion stays.

**Guard 2**: re-read and confirm before writing, in `WPCPM_Mentor_Checker_Runner::promote()`'s shape: already the target, leave unchanged; not a tracked active status, refuse naming what it says; **a current status of `Paused` refuses**, naming view `viwzSJspvACLnhXom`, because the mirror automation is restricted to that view and Paused is not in it, so a Paused row marked Graduate would mail the student while the reports row, the account and the mentor's list stayed Paused. The refusal is removed with a test when the base owner adds Paused to the view (open question 4).

**Guard 3**: the confirm names the email. *Mark Anna Nowak as graduated? Airtable will email her a certificate notice that cannot be recalled, and the change is logged under your name.* No "un-graduate" on the institution's screen; a manager changes it in Airtable, and the screen says so.

### 7.11 Leaving

- **The sync's revoke phase** (an institution leaving `institution_active_stages`): `detach( ..., 'revoked' )` per member; the agreement option deleted; the account kept.
- **Member removal**: section 7.5. The last member's removal cancels every pending invitation and notifies the managers; the record, the agreement, the roster stamps, the audit history and the reports are untouched.
- **Retention**: `withdrawn` and `returned` agreement files after `agreement_discard_days` (30); `accepted`, `superseded` and `revoked` files never, they are the agreement.
- **Uninstall**: `WPCPM_Institution_Agreement::delete_all()` removes posts, options, crons and locks and **keeps the files**, recorded in `uninstall.php` beside the sentence that accounts are people. Before deleting the posts it mails the site administrator (`admin_email`) a list of every kept file: institution, record ID, state, accepted date, relative path, sha256. It writes that list to disk only when `wpcpm_private_probe` last recorded the directory as blocked, as `wpcpm-private/agreements-index-<32 hex>.json`, naming the file in the mail. A fixed-name manifest in a directory the host may serve would be a directory listing under another name.

---

## 8. The syncs, the settings, and the two statuses

### 8.1 The students sync's Students-table pass

`phase_tutors()` keeps its key (a run in flight resumes from a stored phase name) and its label becomes "Reading the Students table". It requests these columns, every name added to `WPCPM_Mentors_Sync::fields()` and asserted against `bin/fixtures/students-table-fields.json`:

```php
'student_record_name' => 'Full Name',            'student_email'  => 'Email',
'student_status'      => 'Status',               'student_institution' => 'Educational Institutions',
'student_start'       => 'Start Date',           'student_end'    => 'End Date',
'student_mentor'      => 'Mentor',               'student_profile' => 'WP Profile',
'student_tutor'       => 'Tutor ',               'student_tutors' => 'Tutors official',
'student_study'       => 'Your field of study',  'student_access' => 'Accessibility needs',
'student_import_key'  => 'Site import key',
```

It builds `$state['rows']` keyed by Students record ID: `record_id`, `name` (trimmed), `email`, `email_key`, `status`, `institution` (first ID of the link, or `''`), `start`, `end`, `has_mentor`, `username`, `field_of_study`, `tutor`, `import_key`, `reports` (filled in `phase_provision()`), `user_id`. `Accessibility needs` is read for `wpcpm_student_program` as today and asserted absent from every index row.

`phase_provision()` resolves each reports row's Students rows by `email_key`: exactly one, or several agreeing on `institution`, stamps that institution with `institution_source = 'students'`; none stamps the reports-side link with `institution_source = 'reports'` and counts it; several that disagree delete the stamp and count `conflicts` with the reason "duplicate email in the Students table". `revoke_departed()` clears the stamp as before.

`finish()` groups rows by institution and writes `wpcpm_roster_<record id>` (`update_option( ..., false )`, `array( 'v' => 1, 'read' => $started, 'rows' => ... )`), `wpcpm_roster_unlinked` for the 3 institution-less rows (manager screen only), and `wpcpm_roster_counts` (institution to cohort to the seven buckets, from `participation()`). `finish()` is the only writer, so a failed run leaves last run's options in place. The state option roughly doubles; the row shape is stripped to what the index needs and holds no free text and no accessibility disclosure.

**The manager reconciliation card** replaces the first spec's "both link counts per institution": Students rows with no reports row by status (31), reports rows with no Students row by status (19), status disagreements (10), duplicate emails per institution (9), rows with no institution (3), tracked accounts with no stamp (should be 0; anything else is a broken sync), contacts who are not members, import batches with a high blocked ratio, and the "Link to institution" control for unlinked rows with its refusals. The first spec's prerequisite ("reconcile the 10 institutions") becomes "work this card down on the rows that matter", owned by a program manager, with a date.

### 8.2 `WPCPM_Institutions_Sync`

Phases `countries` (15), `records` (45), `provision` (25), `revoke` (15), unchanged in order and count; no `cohorts` phase. The `records` phase requests an explicit `fields` list (the prose excluded, the comment recording the `phase_lookups()` bug that read `Current Stage` as the name) that now includes the eight agreement columns, stores status, kind, accepted-on and template version on each index row (no Drive link in the index; it lives on the per-institution agreement option), and rebuilds every `wpcpm_agreement_<record>` option (T12). `WPCPM_Countries` refreshed at activation, by the phase and by a button. `provision` off by default (`institution_provision`), per-row and bulk controls both gated on the recorded agreement state for Confirmed institutions. Daily, offset from the students sync.

### 8.3 Settings

Into `defaults()` and `save()`'s sanitize loop; field names stay out of settings per the class docblock:

```php
'countries_table' => 'tbltB7GSRoTtSi4Ps', 'countries_name_field' => 'Name',
'institution_new_stage' => 'First Contact Made',
'institution_active_stages' => array( 'First Contact Made', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Call Scheduled', 'Agreement Sent', 'Confirmed', 'Student' ),
'institution_provision' => false, 'institution_on_inactive' => 'revoke', 'institution_home' => true,
'applications_enabled' => false, 'application_spam_days' => 30, 'application_rejected_days' => 365, 'application_approved_days' => 0,
'agreement_max_mb' => 10, 'agreement_uploads_per_day' => 5, 'agreement_review_days' => 3, 'agreement_notify' => '', 'agreement_discard_days' => 30,
'import_enabled' => false, 'invite_retention_days' => 30,
'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
```

`WPCPM_Settings::maybe_upgrade()`, keyed by a `SETTINGS_VERSION` option in the shape of `WPCPM_Roles::maybe_upgrade()`, appends `Paused` and `Pending graduation` to the saved `student_statuses` once. Both syncs build their formula from the saved list, so until it holds them no Paused student is fetched and every line of code looks correct; the Developer Track shipped with a manual step for the same trap, and this closes that gap with code.

`WPCPM_Airtable`: a token bucket and 429 handling with `Retry-After` in `request()`, because two live-read paths reachable by a Subscriber-based account can otherwise take the base offline for everyone; `formula_in( $field, $values, $lower = false )`.

### 8.4 Paused and Pending graduation (decision 21), its own release

Both syncs page them; mentors see them under "Currently mentoring" with `Paused` / `Pending graduation` badges; the students sync provisions the nine, invited only if `send_welcome_email` is on. What those nine see on the Student Report Card: the program line prints the raw status; "My course" is absent because `course_url()` is empty, so the hours box inside it is absent too (open question 7); the report form falls back to the 150-hour field set because `WPCPM_Program::track()` is `''` for both: **the track is lost when a student is paused**, right for all thirteen today (150-hour institutions) and wrong for the first 50-hour exception. The four Fidelitas students Paused on Students and Not moving forward on Students Reports appear on the roster as current with "no site account: the program's records disagree" and on the reconciliation card.

### 8.5 `hours_target()`

`WPCPM_Program::hours_target( $status )` beside `labels()` and `courses()`, filter `wpcpm_program_hours_targets`: `In Sensei` 150, `In Sensei 50h` 50, `Developer Track` 0 meaning no target, with `has_hours_target()` for callers. `Hours` joins `WPCPM_Mentors_Sync::fields()` as `report_hours`, `phase_reports()`'s list and `apply_report()`'s map in Phase 5.

---

## 9. Data model

### Post types (all `private`, `show_ui` false, `show_in_rest` false, a capability type nothing is granted, `map_meta_cap` true)

| Type | One per | Meta |
| --- | --- | --- |
| `wpcpm_inst_app` | submission | section 7.2 |
| `wpcpm_agreement` | document | `_wpcpm_agr_institution` (the queryable key), `_wpcpm_agr_state`, `_wpcpm_agr_kind` (`template` / `own` / `legacy`), `_wpcpm_agr_language`, `_wpcpm_agr_template_version`, `_wpcpm_agr_name_on_document`, `_wpcpm_agr_file` (`path`, `size`, `sha256`; absent on generated and legacy rows and after discard), `_wpcpm_agr_original_name` (display only), `_wpcpm_agr_flags`, `_wpcpm_agr_drive_url`, `_wpcpm_agr_signed_on`, `_wpcpm_agr_note`, `_wpcpm_agr_decided_by` / `_at`, `_wpcpm_agr_airtable_pending`, `_wpcpm_agr_event` (repeating) |
| `wpcpm_import_batch` | staged batch | `_wpcpm_batch_institution`, `_wpcpm_batch_state`, `_wpcpm_batch_rows`, `_wpcpm_batch_values`, `_wpcpm_batch_confirmation` |
| `wpcpm_inst_request` | `add`, `mentor`, `format` | `_wpcpm_req_kind`, `_wpcpm_req_institution`, `_wpcpm_req_student`, `_wpcpm_req_state` |
| `wpcpm_audit_entry` | applied change or membership event | section 5.6 |
| `wpcpm_inst_report` | institution and cohort | section 7.9 |
| `wpcpm_inst_invite` | invitation (Phase 4) | section 12 |

`uninstall.php`'s `require_once` list gains every new class file (and `class-wpcpm-sponsors.php`, which it is missing today while `WPCPM_Modules::uninstall()` instantiates it), and `bin/test-roles.php` asserts the parallel lists.

### Options (all `update_option( ..., false )`, versioned, discarded on mismatch)

`wpcpm_institutions_index`, `wpcpm_countries`, `wpcpm_roster_<record>`, `wpcpm_roster_unlinked`, `wpcpm_roster_counts`, `wpcpm_agreement_<record>`, `wpcpm_private_probe`, `wpcpm_agreement_drift`, `wpcpm_application_log`, `wpcpm_import_log`, plus the `add_option()` lock and bucket keys. No prose, no accessibility disclosure, and the Drive link only on the agreement option.

### User meta

Section 5.1 for institution accounts; `wpcpm_student_institution` and `institution_source` (inside `wpcpm_student_program`) for students; `wpcpm_report_permissions` and `wpcpm_report_consent_asked` for the report.

---

## 10. New Airtable fields

Created by the developer through the API, each announced first (decision 17), on the live base after the TEST institution record exists. Choice names are pinned in the fixtures and asserted byte for byte, because `update_records()` sends no `typecast` and a choice spelled any other way is a 422 for the whole PATCH. The base's own casing is mixed (`Contact Email`, `Confirmed on`), so the fixture is the authority for every name below.

| Table | Name | Type | Options | Why |
| --- | --- | --- | --- | --- |
| Institutions | `Agreement Status` | singleSelect | `Not started`, `Template generated`, `Awaiting review`, `Accepted`, `Returned`, `On file`, `Revoked` | the system-of-record half of `is_settled()`; a hand edit in the grid takes effect on the next rebuild; empty on the 105 existing records reads as `Not started` |
| Institutions | `Agreement Kind` | singleSelect | `Program template`, `Institution-specific`, `Legacy` | which branch the agreement in force came from, so the bespoke agreements can be listed; separate from Status because it describes the document, not the workflow |
| Institutions | `Agreement Accepted On` | date | | the date a program manager accepted or recorded the agreement on the site; distinct from `Confirmed on`, which the deployed automation owns and this module never writes |
| Institutions | `Agreement Signed On` | date | | the date the institution signed, entered by a manager for a legacy copy; kept apart from the acceptance date so per-version listings and queue ages do not misread the 42 |
| Institutions | `Agreement Accepted By` | singleLineText | | the manager's display name, so the base answers "who said yes"; the WordPress user ID is on the post |
| Institutions | `Agreement Document` | url | | **Drive links only**: where a legacy agreement is, pasted by hand because Drive folders cannot be matched in code (fact A). Empty for a site-accepted row; the site is where that file is. Required for `On file` to settle |
| Institutions | `Agreement Submitted On` | date | | when the latest signed copy landed on the site, for queue age in the base and for the program's own reminder automation if it wants one; updated on replacement uploads |
| Institutions | `Agreement Template Version` | singleLineText | e.g. `2025-11-04 (en)` | which wording this institution signed, empty for bespoke and legacy; the template card lists institutions per version so re-signing is a program decision |
| Students | `Site import key` | singleLineText | | written once per row created by the site (`imp-<batch>-<row>`) and never edited; a retry after a lost response searches it before creating again, the only way to tell "created a second ago" from "somebody else enrolled this student" |
| Feedback | `F3 - Report: my institution may list me in its semester report` | singleSelect | `Yes, with my name`, `Yes, by my blog address only`, `No` | the student's own release of their name and blog links into a document sent to their university; written only by the student's own save |
| Feedback | `F3 - Report: my institution may quote my feedback in its semester report` | singleSelect | `Yes, with my name`, `Yes, without my name`, `No` | the student's release of the "proud of" answer the plugin's docs promise the institution never sees; the existing public-sharing column is a different consent and is empty on all 834 rows anyway |

Nothing else. Cohorts derive from the existing `Start Date`; site membership is site state; report drafts live on the site; `Contact Email` is never rewritten by a membership change. Fields recorded as **not to be created** are in section 12.

---

## 11. Build phases

Re-derived with the gate in front of everything. Every real Confirmed institution, the only pilot candidates, is legacy, so the first thing a pilot needs is the "on file" state, not the generate-and-upload path; and nothing an institution can see ships before the policy, the gate and the on-file route exist together. Each phase is a version bump (header, `WPCPM_VERSION`, every `block.json` and `editor.asset.php`, `readme.txt`), an installable zip, and a mirror push.

### Phase 0: the columns and the shared files, each its own commit

The eleven Airtable fields, announced and created, and the fixtures refreshed (`institutions-table-fields.json` with the eight columns and their choices; new `students-table-fields.json` with 29 names (the 28 plus `Site import key`) including `Tutor `, 14 Status choices, 9 field-of-study choices and the coverage facts; new `feedback-table-fields.json`; `agreement-template-en.json`). `WPCPM_Settings::defaults()`, `save()` and `maybe_upgrade()`. `WPCPM_Airtable::request()`'s throttle and `formula_in()`'s third argument. `WPCPM_Mail::send_to()`, the `invite-institution` branch with its two wordings, the third arm in `drain_queue()` and `queue_invites()`. `WPCPM_Field_Value` hoisted. `WPCPM_Module::menu_label()` and `WPCPM_Admin::register_menu()`. `assets/js/forms.js` extracted from `calendar.js` with `bin/test-submit-guard.php` repointed in the same commit. `admin.js`'s progress selector widened. The loader and `uninstall.php` lists, `bin/check-references.php` run.

**Demonstrates**: every test suite green with the fixtures; `formula_in( 'Email', ..., true )` wraps the field in `LOWER()`; a forced 429 returns a distinguishable `WP_Error` and the next request waits.

### Phase 1: the join, the index and the manager screen. Manager-only.

`META_INSTITUTION` stamped from the Students side with `institution_source`; the Students-table pass and the per-institution index; `wpcpm_roster_counts`; `WPCPM_Cohort`; `WPCPM_Countries`; `WPCPM_Institutions_Sync` with `countries` and `records` reading the eight columns and rebuilding `wpcpm_agreement_<record>` (T12, including materialising legacy posts from grid-recorded `On file` rows); `WPCPM_Institution_Agreement::is_settled()` with the grid route as the only way to open a gate; `WPCPM_Institution_Members` and `WPCPM_Institution_Policy` with the manager and member grounds; `notify_managers()` as a shell; `is_implemented()` true; the manager screen with the sync panel, the stage-grouped pipeline with its agreement column and the "Confirmed with no agreement recorded" filter (42 rows on day one), the reconciliation card, the consent report, the template card without the drift button, and the storage card with the probe. The `student_statuses` upgrade ships here too, as its own commit, with its release note.

**Demonstrates, against a fixture-seeded index and the TEST record**: the index holds every row of the seed fixture with the stage counts the fixture records; every country an institution names resolves to a name, and the ones with no contact are listed as routing gaps; the consent report reads the fixture's pre-20-July count and never "lost"; the no-stamp count reads zero and the reconciliation card reads 31 / 19 / 10 / 9 / 3; a Krakow-shaped fixture (15 rows: 8, 2, 0, 0, 5, 0) yields 15 / 8 / 2 / 5 from `participation()`; typing `On file` and a Drive link into the TEST record's grid row and pressing Refresh settles it, and typing `Revoked` locks it on the next rebuild; a `decide()` against the TEST record refuses a member while locked and passes a manager; the probe records what the host does. Nine Paused and Pending graduation accounts exist and their mentors' lists show the badges.

**Prerequisite, owned by a program manager, with a date**: the 10 trailing-space names and the 2 nameless records, and the first pass over the reconciliation card.

### Phase 2: accounts, the gate, the on-file route, and the read-only roster. The pilot.

`provision` and `revoke` (per-row control gated on the recorded agreement state); `attach()` / `detach()` wired to them; the manager's add-account, re-add and remove; `handle_on_file()` and the bulk button's refusal; the dashboard page, block, shortcode and stylesheet; the locked-panel render branch and the manager banner; `resolve_institution()` and the switcher keyed by record; the four roster groups, the "Not yet in the Students table" list, the cohort picker and the comparison strip; the detail view with `claim()` on the report route; the People card with Remove; the agreement card at the foot; `WPCPM_Dashboards::links()`; the audit log shell with the ground keys.

**Demonstrates, on the TEST institution with a team member's address**: with no recorded agreement the member sees the identity header and the panel and nothing else, and direct requests to the detail view, the report REST route and both exports each get the one refusal; recording "on file" with an `example.com` link is refused and with a Drive link opens the account on the next page load; the roster shows the TEST students in the right groups with the index read time; the picker lists the fixture's cohorts and the strip reads their two numbers; `?wpcpm_institution_view=` works for a manager and does nothing for a member; moving the TEST record out of the active set revokes the member, moves the stamp and reads an empty roster afterwards; removing the last member cancels nothing (no invitations exist yet) and mails the managers. **Then the pilot**: one real Confirmed institution, its Drive link recorded, one named contact provisioned per row, signing in and seeing only its own students with the count the index gives. Krakow's 2026-H1 15 / 8 / 2 and D. Y. Patil's 2026-H2 41 plus 2023-H2 1 are pilot acceptance checks, not build gates.

### Phase 3: the application form and the agreement path, together

Track A (A1 form and held posts, A2 the shared queue with the menu bubble, A3 approval with the index insert and the attach) and the generate / upload / download / withdraw / accept / return handlers, `WPCPM_Agreement_Template` with its fixture, `WPCPM_Private_Files`, `agreement-landed`, the five member mails, the discard cron. A3 is the first user of the generate path, which is why they ship as one release. The Airtable form stays live as production intake until this ships; A1 and A2 run as a staging pilot.

**Demonstrates**: consent absent, `"0"` and `"yes"` each refuse and store nothing; a harvested dwell token refuses on second use; forty posts from one source do not close the form to another; approval refuses an unverified address and any address holding a WordPress account, creates the record at `First Contact Made` with `Privacy Policy Compliance` boolean true and `Agreement Status` `Not started`, inserts the index row, attaches the account, and mails the new-institution wording; a rejection mails a neutral acknowledgement with no reason and spam mails nothing; the generated document names the institution twice with no `[` surviving and the footer carries the version; a renamed `.exe`, a `/Encrypt` PDF, a 12 MB file and a sixth upload each refuse with the named reason and nothing lands in the private directory; a good upload lands with a 32-hex name and mode 0640; Accept writes the seven cells and `Confirmed` in one PATCH, the automation fills `Confirmed on`, the member's next page load is the full dashboard, and the mail log shows `agreement-accepted` once per member; Return mails the note verbatim; Accept at `Not Moving Forward` refuses by name; a member of B withdrawing A's post gets the one refusal.

### Phase 4: editing, notes, requests, members' invitations, revoke and reminders

The allowlist and `handle_save()`; `claim()` on every write; the audit log proper; institution notes; `wpcpm_inst_request` with the mentor kind and its queue; `wpcpm_inst_invite` with `handle_invite`, the `nopriv` `handle_accept`, cancel and resend, and the People card's invite form; `handle_revoke()` and `handle_reinstate()`; `CRON_REMINDERS`; the drift button; manager generate and upload on behalf.

**Demonstrates**: an end date edit lands in Students, the mirror carries it, the student's own Student Report Card shows it without a sync, one audit row names actor, field, both values and the `member` ground; a `curl` POST with a field outside the allowlist changes nothing and one naming another institution's student gets the same message as an unknown record; a student with three Students rows renders the Students half read-only; an invitation token is stored hashed, acceptance signed in as another address is refused, an expired and a cancelled token give one message, the eleventh pending invitation is refused; Revoke locks the member's next page load and deletes the option, Reinstate reopens it; a four-day-old item appears in the digest once and not the next day; the drift check against the unchanged Doc matches.

### Phase 5: import, graduation, hours, exports

`WPCPM_Institution_Import` with its ceilings and neutral verdict; the `provision_student()` conflict rule; `states()` with the three guards including the Paused refusal; `Hours` through both syncs and `apply_report()`; `hours_target()`; both CSV exports with the apostrophe prefix and the BOM; the unlinked-row link control on the reconciliation card.

**Demonstrates, with team members' addresses on TEST students**: a batch of five with one duplicating an existing email and one duplicating a row in the same file creates three and names the other two, the outside hit reading only "cannot be imported from here"; a sixth check in an hour is refused before parsing; a forced lost response on row two produces one record on retry; a revoke between two slices creates nothing further and parks the batch; a manager import through the switcher stamps the switcher's record and a manager with no switcher is refused; a name beginning `=` is refused at the door; each created student shows under "Waiting for a mentor" and (after a mentor is assigned to a TEST student whose address is a team member's, so the welcome mail reaches the team) moves to "Current" after the next sync; graduating a Paused TEST student is refused naming the view; a graduation writes only the Students table and the confirm named the email; an export's totals match the roster.

### Phase 6: the semester report

`WPCPM_Semester_Report`, the two Feedback fields on the Finishing-up form with the self-only rule, `record_for()`'s institution link, the consent re-check, the print document, `ACTION_ASK`.

**Demonstrates, on the TEST institution with a fixture-seeded index and TEST students answering the consent questions themselves**: Participation reads the fixture's numbers with the index read time; Student Projects lists exactly the students who answered yes, with the label their answer chose; a manager posting a permission value for a student writes nothing to Airtable and stamps nothing; a student changing an answer to `No` disappears from the next render and the next export without regeneration, and the page says so; a second member's stale save is refused and the text stashed; the print document contains no theme part and every anchor shows its href as text; `ACTION_PRINT` with another institution's post ID gets the unknown-report message.

---

## 12. Designed for, not built

- **Joint projects (decision 14).** What two institutions share is report detail about one project, never students, cohort, emails, hours or grades. The fence already has what a second clause needs: `decide()` returns a `fields` scope every caller passes through `scope()`; `grounds()` is a full explicit map, so the clause is two rows (`ACT_VIEW_SEMESTER_REPORT` and a future project view) and a failing assertion until updated; grounds are tried in order so an outsider reaches the project ground only after the member ground declined; subjects carry `institution_ids` and can carry `project_ids`; the log stores the ground. What it lacks, and would need: a `Projects` table (Name; Institutions link; Students link; Contribution team link; Summary; Links; Start; End; Status `Proposed` / `Active` / `Completed` / `Closed`; `Sharing confirmed` checkbox and date), a reverse `Projects` link on Students, a sync slice, a `wpcpm_student_projects` stamp, a `PROJECT_FIELDS` list (a program decision, open question 8), the students' consent, and a manager screen for confirming sharing. **None of the Airtable fields in this paragraph is to be created on the strength of this document.**
- **The Airtable copy of the signed file.** An `Agreement File` attachment column filled through `content.airtable.com`'s upload endpoint (5 MB ceiling, `data.records:write`) would put the accepted PDF on the record itself and survive an uninstall without the mailed list. Not scheduled; the endpoint's contract is to be verified against current documentation before the column is created.
- **A countersigned copy.** A second file on the same post, `_wpcpm_agr_countersigned`, uploaded by a manager through the same store and served through the same handler. Whether the program countersigns at all is open question 11.
- **Other agreement languages.** `load( $language )`, `languages()`, a picker that appears only when there are two, `_wpcpm_agr_language` on every post. Which siblings exist is open question 11.
- **Re-enrolment and transfers.** A graduate returning for a second track, or a student moving institutions, is `blocked` at import and referred to a manager. The shape that would serve it is a request kind `reenrol` a manager resolves by creating the second Students row by hand.
- **A second membership per account.** The storage and `memberships_of()` can hold it; `attach()` refuses it until somebody needs it and says who.
- **A per-student project summary line in the report**, and a countersigned or signed server-rendered PDF. A renderer for the agreement alone is the fallback if the program wants a logo and a serif face; nothing else moves.

## 13. Deliberately not in scope

- **In-app mentor acceptance.** The `wpcpm_inst_request` queue records that a mentor is wanted; it does not negotiate one.
- **Replacing the Sensei grading system.**
- **Any electronic signature.** The product owner said offline signing; a drawn signature would make the plugin a party to a question of authority it cannot answer.
- **Backfilling `Privacy Policy Compliance`** on the 63 historical records, or inventing 105 application posts. Writing "they consented" where there is no evidence is worse than the gap.
- **Auto-matching Drive folders to institutions** (fact A), and changing an institution's Airtable `Name` from the generate form.
- **A CAPTCHA or a virus scanner.** The honeypot, the bound dwell token, the limiter and human review carry the form; the type ladder and download-never-inline carry the upload; a scanner is a host service.
- **Machine translation of quotes**, any feedback rating in the report, a public or signed URL for the report, emailing the finished report to anyone.
- **Tiers inside an institution**, a members list on the institution, a capability for membership (`SCHEMA_VERSION` stays at 2), auto-provisioning from the `Tutors` link, mirroring site membership into Airtable.
- **A cached roster in user meta, a custom database table, a second Airtable client, a chart on the cohort table.**
- **Re-signing when the template changes.** Reported per version; decided by the program.
- **Retro-editing em dashes out of existing files.** New code uses hyphens; existing UI strings are left alone.

## 14. Open questions for the product owner

All sixteen are now answered: six by the product owner (1, 3, 4, 5, 11, 13), two by the host itself (9, 10), and the eight the product owner handed back (2, 6, 7, 8, 12, 14, 15, 16) settled by the developer on 2 September 2026 and marked as such. They are kept in full rather than deleted, because the reasoning is what a later reader needs when one of them is reopened.

1. ~~Does "graduated" include Pending graduation?~~ **Answered by the product owner, 2 September 2026.** No. Pending graduation means the student has not completed the program yet; it is a different state from Graduate and is never folded into the graduated count. The roster, the cohort comparison and the semester report print it as its own line.
2. ~~Who may send the consent request to the 289 students who have never been asked?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **Program managers only.** The institution is the party that benefits from a yes, which is the wrong party to do the asking, and the addresses belong to the program rather than to the school. The institution sees how many of its students have not been asked and may ask a manager to send; the manager sends through the same queue the invitations use, so the sends are logged, rate-limited and stoppable. Graduates who never log in again will not answer whoever asks, so the first reports print the aggregate figures and name only those who said yes.
3. ~~Who reads the mentor request queue, and after how many days is a "waiting for a mentor" item overdue?~~ **Answered by the product owner, 2 September 2026.** The program manager for the institution's country, as the Countries table's `Person of contact (Team)` already routes. A student is overdue after **14 days** without a mentor: long enough to cover a holiday, short enough that a cohort cannot quietly stall. The "Waiting for a mentor" group shows the days waiting and marks the overdue rows; the country manager is who the count is for.
4. ~~Will the base owner add `Paused` to view `viwzSJspvACLnhXom`, and have the creation automation copy `WP Profile` and `Your field of study` to the reports row?~~ **Answered by the product owner, 2 September 2026.** Yes: both automation changes are the product owner's to make, outside the developer's announced-field procedure. Until `Paused` is in view `viwzSJspvACLnhXom`, an institution cannot graduate a Paused student, and the roster says so on the row rather than failing silently.
5. ~~Can the "WordPress Credits - Student Registration Form" be closed, or refuse an existing address, for institutions that import?~~ **Answered by the product owner, 2 September 2026.** Prevent duplicates. The registration form is an Airtable form and cannot check uniqueness, so prevention means an imported student is never sent to it: the site's import and invitation replace it for institutions that import, the invitation email says so, and the sync flags any email that appears on more than one Students row for a program manager to merge. What the form collects that an import does not is an open item for the base owner, not this module.
6. ~~May an institution edit a student's email address, and should the write also change the WordPress account?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **No to both, and it stays out of the allowlist.** The address is the only join between the Students and Students Reports tables and it is the account's identity, so an institution editing it would re-point the join and could walk an account onto an address it controls. A correction is a request to a program manager, who changes it in the base; the sync carries the new address, and the WordPress account's email is changed by nobody from this module.
7. ~~Should a Pending graduation student be able to log hours?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **No.** Pending graduation means the work is done and the paperwork is not; a student still doing work is active or paused, and an institution that finds otherwise moves the status back. The rule that no course URL means no hours box then holds with no exception, which is worth more than the handful of hours it might collect.
8. ~~Which fields are "details related to the project" for a joint project, and is the student's name among them?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **The project's own facts, and no, not the name.** `PROJECT_FIELDS` is the project's name, summary, links, contribution team, dates and status, plus counts of the students who worked on it. Never a student's name, address, blog, grades, hours or status. A partner institution learns what was built, not by whom. Naming a student to another institution needs that student's own yes, which the two report-permission columns added in Phase 0 already model, and which nothing in this design reads for this purpose yet.
9. ~~What is the real client IP on WordPress.com Atomic?~~ **Answered, 2 September 2026, from the host itself.** The site has no `trusted_ip_header` option, so `Automattic\Jetpack\IP\Utils::get_ip()` reads `REMOTE_ADDR` and never a forwarded header, which is what makes it unspoofable here. Jetpack Protect is active and blocks by exactly that value; were it the edge address, one failed-login streak would lock out every visitor, and that does not happen. The rate limiter and the consent record use `IP_Utils::get_ip()`, the platform's own resolver, rather than reading `$_SERVER` directly. This is no longer open.
10. ~~What does Atomic do with a direct request to `/wp-content/uploads/wpcpm-private/`?~~ **Answered, 2 September 2026, by probing the host, and then answered again when the product owner said the host would not add a rule.** A file that exists under `wp-content/uploads` is served by nginx to anyone who knows its name, the host offers no writable directory outside the web root, and asking the host to block the path is not available. So the design does not depend on the host doing anything:

    - **The directory's name begins with a dot.** Measured on this host the same day: the same file answered 200 with its body from `uploads/wpcpm-probe-plain/` and 403 with none from `uploads/.wpcpm-probe-dot/`, and a dotfile inside a plain directory answered 403 too. The rule that refuses dot-prefixed path segments is nginx's own and needs nobody's cooperation. `probe()` measures the real path against a plain control path on every run, so the storage card can say whether the refusal is the dot doing the work or the host refusing everything, and would notice the day the rule changed.
    - **Every stored file is encrypted**, AES-256-GCM, with a per-site key made once and held in a non-autoloaded option. The plaintext is never written: `store()` encrypts in memory and writes ciphertext, so there is no window in which a readable copy exists. The key is in the database and the bytes are on disk, so reaching a document means reaching both stores. A file changed behind the plugin's back fails its tag and is refused rather than handed to a reviewer as the document that was signed.
    - The extension is an allowlist of one, `pdf`, because the directory sits under the document root and an accepted extension is a name the host could one day be asked to execute.

    On top of that the plugin still never prints a file's URL, names carry 128 bits of entropy, and every download goes through the handler that checks the capability and the institution first. `WPCPM_Private_Files::store()` and `read()` are the only way in and out; `bin/test-private-files.php` pins that no plaintext byte reaches the disk, that two copies of one document do not look alike on disk, and that a flipped byte is refused. **Two consequences to carry into later phases**: a host backup that skips dot-prefixed directories would skip the agreements, so the storage card names the directory for whoever configures backups; and `uninstall.php` keeps the files, which means it must not delete the key or must decrypt them in place first, or the kept files are unreadable. This is no longer open.
11. ~~The agreement's administration~~ **Answered by the product owner, 2 September 2026.** The WordPress Foundation owns the Doc's wording and countersigns. The template exists in English only; an institution needing other terms or another language uses the bring-your-own branch, which a program manager reads in full. An institution may state its printed name, editable and recorded beside the Airtable name.
12. ~~Where does a mentor-plus-member account land on login, and should `ACT_MANAGE_MEMBERS` be exempt from the gate?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **The mentor dashboard, and no exemption.** Mentoring is the time-critical half: students book calls and wait for replies, while a member's first task is the agreement, which the invitation links to directly. Both dashboards stay one click apart in the toolbar. `ungated()` keeps its single entry, because the gate is worth having only while it is one rule that a reader can hold in mind; a manager adds the signatory's account in the meantime, which is a person deciding rather than a rule with a hole in it.
13. ~~Retention and ceilings to match the published privacy notice~~ **Answered by the product owner, 2 September 2026.** The proposed figures stand: 30 days for spam and 365 for a rejected application (section 8.3's `application_spam_days` and `application_rejected_days`); consent records and accepted agreements for as long as the institution exists; 30 days for a withdrawn or returned agreement file; 7 and 30 days for import batches; invitations valid 14 days, 10 pending per institution, 5 sends per member per day; agreement uploads 10 MB and 5 a day. Checked on 2 September 2026: **the site had no privacy notice at all** - no designated privacy page and no page mentioning privacy. Applicants were pointed at wordpress.org's policy, which covers program data as "retained for as long as necessary to administer the relevant program" and is compatible with these figures but does not describe this site. A notice has been drafted as page 654, "Privacy Notice", status draft, stating these figures in plain language and who sees what; it names the WordPress Foundation as controller and leaves the contact address for the Foundation to confirm. Publishing it and setting it as the site's designated privacy page are the product owner's actions. Until it is published, the application form's consent text links to wordpress.org's policy as today; once published, it links to page 654 and the stored consent wording records which. The point of all of this is that the stored consent text and the actual retention behaviour cannot disagree - the class of defect this work exists around.
14. ~~The page title for slug `institution-dashboard`, and what the institution may see.~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **"Institution Dashboard"**, matching the Student and Mentor dashboards it sits beside, with `TITLE_VERSION` at 1 so the product owner can rename it in one place and have every site follow. `Accessibility needs` stays excluded from every column, detail row, export and report: it was disclosed to the program to be accommodated, not to the school. Course grades stay where the design puts them, the single-student view and the single-student export, and appear in no list and no semester report.
15. ~~Should the accepted PDF also be pushed onto the Airtable record, and should Airtable carry a read-only "Site accounts" count?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **Neither.** The Foundation's Drive folder is where signed agreements live, and a second copy in a second system is a second place a mistake has to be corrected; the uninstall mail already lists every file kept on the site. The accounts count would be a second record of a fact WordPress owns, which is the shape decision 6 exists to refuse. Both remain one announced field away if the program later wants them.
16. ~~Should a program manager be able to act on behalf of an institution at an unsettled stage?~~ **Decided by the developer, 2 September 2026**, on the product owner's instruction that the remaining questions were the developer's to settle and record. Any of these is one line to reverse. **Yes, as designed, logged as `manager`, the semester report included.** The gate limits what an institution may do for itself while nobody has accepted its agreement; the manager is the person it is waiting for. Applying it to managers would mean that the moment an institution is stuck, the one person who can unstick it is locked out too.

## 15. Risks

- The gate's answer is a cached one for base-side edits: a `Revoked` typed into the grid takes effect on the next sync or Refresh, up to a day. Every site-side transition is immediate.
- Manager availability is the queue's latency. The landed mail, the digest and the menu bubble make the wait visible; they do not shorten it.
- The private directory may not be private on Atomic. The probe says what the host does; until the host blocks the path, "never guessed at by URL" rests on 128 bits of entropy and the download handler.
- Consent for the report starts at zero on 834 rows.
- The registration form view creates duplicates the plugin can only surface.
- The legacy path is 42 hand-pasted Drive links against a folder tree with free-form names; it is a morning's work for one manager and it is on the critical path to the pilot.
- Browser print-to-PDF differs across Chrome, Safari and Firefox in page breaks and link handling; the version footer and the visible URLs are what survive the differences.
- Signed files survive uninstall in a directory the next site owner may not know about; the mailed list is the mitigation.
- Every member is mailed at every agreement transition; for an institution with six accounts a return note reaches six inboxes, with reply-to the manager who wrote it.
- The undeployed `New partnership confirmed` Slack automation will post this site's acceptances if a manager deploys it later. Intended, and named here so it is not a surprise.