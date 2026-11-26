# Students and mentors off Airtable (design, 20 November 2026)

Plugin: WPCredits Program Manager, main at b090cfde (1.123.106, live). The last of the three moves of the Airtable exit,
after institutions (setting `institutions_on_site`, switched on Monday 30 November 2026). Groundwork:
`.superpowers/sdd/2026-11-20-mentors-students-exit/inventory-and-proposal.md`, cited as "inventory" with its section
numbers. Model for shape: `docs/specs/2026-11-11-institutions-off-airtable-design.md` and its plan
`docs/plans/2026-11-11-institutions-off-airtable.md`. Screens: `docs/specs/2026-11-13-ui-shared-parts-design.md`.

Paths are under `includes/` unless they say otherwise (`modules/`, `tools/`, `mail/` are folders of `includes/`); a
line number is main at b090cfde. A class, function, hook, setting, cron, option, post type, meta key, capability or
command marked **new** does not exist yet; every other one does. Where the code and the inventory disagree, the code
wins, and the last section lists each difference. This is the second round of the design: a review of the first
(52b2bc9e) against the code found six holes, closed here (the freeze, the day's windows, how an account is found, how
two Students tables pair, the way back, the welcome).

## Decisions for the product owner

Each with the recommended answer. "Free" means the plan as written does not depend on the answer: he may leave it.

1. **The day and hour**: Monday 28 December, from 00:00 UTC, about two hours with him (8.5).
   Recommended: confirm it.
2. **D1, the grid**: Administrators work on the site, with the frozen base readable for 30 days. Recommended: yes.
3. **D3, the hosting plan**: the Foundation owns and pays, daily backups kept 30 days. Recommended: yes, named before
   21 December. Free for the build; needed for the day.
4. **D5, a copy back**: none. Recommended: none.
5. **D6, the outside readers**: one public feed; an in-process read for Student Impact; the WPCredits Tracker reads the
   feed. Recommended: yes; retire the Tracker only if nobody reads it.
6. **D7, the Airtable forms**: all close on Thursday 24 December. Recommended: yes.
7. **D8, the email texts**: the person who owns them pastes the private parts in Tools > Emails by Monday 21
   December. Recommended: name that person now.
8. **D9, the two emails for one school**: retired. Recommended: retire, unless that school's Administrator objects.
9. **D10, graduation on the site**: a Graduate action with a day; certificate codes typed in, never in a repository.
   Recommended: yes.
10. **D12, the post-graduation snapshots**: a private store on the site. Recommended: yes.
11. **D15, shared Airtable views**: switched off on Thursday 24 December, each person told on 21 December.
    Recommended: yes.
12. **D16, new tracks**: none published from 1 December until the switch is checked. Recommended: yes.
13. **D17, privacy**: exporter, eraser and retention with the record; the Privacy Notice updated before the switch.
    Recommended: yes, and confirm the two retention numbers (13.3).
14. **Statuses with no state today** (I7): Interested becomes a registration waiting for a decision; In Sensei
    Self-onboarding an enrollment on the 150-hour track at Enrolled; Duplicated is archived and makes no enrollment.
    Recommended: yes. As counted on 20 November, the statuses of all 1,254 Students rows and all 1,236 Students
    Reports rows add up with none at any of the three, so free unless one appears.
15. **The most personal fields wait for the privacy tools** (I11): accessibility needs, feedback free text and
    Administrators' notes join the fill with release 2. Recommended: yes.
16. **Graduate takes today or a later day, never a past day.** A late graduation is dated the day it is pressed, with a
    note. Recommended: yes. Free.
17. **The welcome goes when a mentor is set, or when an Administrator chooses to send it** (10.1). Recommended: yes.
18. **A track can be changed inside an enrollment** while it is waiting or enrolled (3.3). Recommended: yes. If no, a
    correction of a track is closing the enrollment and opening another: one more decision on the card, half a task.
19. **A school recording a drop-out picks a reason** (9.3). Recommended: yes. Free.
20. **The Pending graduation email to the program inbox is retired** with its Airtable form. Recommended: yes. Free.
21. **Feedback rows with no course go on the person's earliest enrollment** (3.4). Recommended: yes. Free.
22. **The Students and Mentors views are shown read-only before the switch** (9.4). Recommended: yes. If no, no task
    is saved, and Administrators first see the views on 28 December: risk 8 of 15.1 grows.
23. **The public feed hides any broken-down count under 5** (12.1). Recommended: yes. Free.
24. **What "done in December" means** (section 17, its nine tasks numbered): (a) the institutions' ties to the base are
    cut in January, or (b) before 28 December. Recommended: (a), unless switching the base off in December matters more than the slack. The
    plan as written assumes (a).
25. **Students told?** Recommended: no email; a line on the Student Report Card during the freeze. Free.
26. **Student Impact shows graduates by name with no consent on record.** This move does not change that; it is a
    question of its own. Free.
27. **The WPCredits Tracker's grade figures end at the switch** (the grades go to the archive, decision 11).
    Recommended: accept. Free.
28. **An inactive mentor keeps their current students until each enrollment is changed**, as today. Recommended: yes.
    Free.
29. **Turning the record on is a command, not the settings box** (8.1), because it runs in steps that must resume; the
    box shows the state and turns it off. Recommended: yes. This differs from the institutions switch, which he kept in
    wp-admin.
30. **A row gone from the base is listed, never removed by the fill** (6.1): it leaves the site's record only on an
    Administrator's command, into a status of its own, never WordPress's trash. Recommended: yes.
31. **The base is read-only for everyone but its owner from the sitting's start** (8.5, step 2b). Recommended: yes.
32. **The freeze does not lift by itself at its until time** (8.2): only the switch, the abandon or `people_freeze off`
    ends it. Recommended: yes.

## 1. Why, goals and anti-goals

The site already holds the accounts, the registration and mentor application forms, the Student Report Card, the
feedback forms, the Mentor Report Card and the Institution Dashboard. The record behind them is still Airtable's. Two
syncs read it every three hours and rewrite the site's copies whole (`WPCPM_Students_Sync`, cron `wpcpm_students_daily`,
at +0:30; `WPCPM_Mentors_Sync`, cron `wpcpm_mentors_daily`, at +1:00). Twenty-seven deployed automations send the
emails, Administrators change states and pair mentors in the grid, and the report answers, the feedback answers, the
graduation dates, the Dropped out reasons and the Administrators' notes exist only there. A copy holds one value per
field, with no history, so a student's second track overwrites the first.

Students and mentors move together because the pairing lives on both sides (inventory 7): moving either first needs a
bridge that is built, used for a few weeks with two places to change a pairing, and thrown away.

**Goals.**

- The site keeps one record per person (the account), one enrollment per track taken, each enrollment's feedback,
  notes and history, and one record per mentor.
- Administrators enroll, pair, pause, graduate and close enrollments on the Administrator Dashboard, and nobody opens
  the Airtable grid for a student or a mentor after the switch.
- Every email Airtable sends about students and mentors today is sent by the site, once, for what happens after the
  switch.
- The outside readers read the site.
- Every release ships with the setting off and changes nothing a person sees, except what this design says it adds.

**Anti-goals.** Each is a failure even if everything else works.

- A student or a mentor wrongly merged with another person, lost in the move, or locked out of their account by it.
- An email sent twice, or not sent when it was owed, because of the move.
- A record changed in two places, a change lost between the last read and the switch, or the site reading Airtable
  about a student or a mentor after the switch.
- A note, a report answer, a feedback answer, a screenshot or a record comment lost.
- Names stored double-encoded.
- Anything a person reads linking to Airtable.
- A test email reaching anyone but the one test address the product owner named.
- Anything deleted for good without the product owner's word.

**Non-goals.** Institutions (moved on 30 November) and how a school made on the site gets its key (8.8, section 17);
the Campus Connect base; the AI Mentor; mentors editing their own directory fields; a redesign of the Student or Mentor
Report Card; bulk changes on the Students view; a tool that merges two accounts.

## 2. The decisions

### 2.1 The product owner's decisions

Of 20 November 2026:

- Students and mentors go "at the same time, as the final step".
- The date: "it needs to be December, can be end but it needs to happen till the end of december." The working date,
  still to be confirmed by him, is **Monday 28 December 2026, from 00:00 UTC** (the hour he set for the
  institutions switch), one sitting of about two hours. The date holds and the scope flexes
  (section 14.6).
- Decision 2, "ok": a returning student gets a new enrollment per track, one current enrollment at a time; earlier
  ones stay readable on the Student Report Card.
- Decision 11, "ok": Students, Students Reports, Feedback and Mentors come over in full, with screenshots, notes and
  record comments; the old lesson form submissions, deleted columns and grades go to a private archive file outside
  every repository.
- Decision 13, "ok": a mentor is Active or Inactive only; rows at Vetted - positive become selected applications;
  Declined, Needs more information and Dropped out rows are archived.
- Decision 14, "ok": rows that are not students (Not moving forward, spam, fail) come over as closed enrollments;
  rows with no status as registrations waiting for a decision.

Standing, not asked again: one person is one account with many enrollments, never a duplicate (18 October 2026); the
WordPress.org profile is the source of truth for who a student is; a mentor is optional, and a student with no mentor
is normal; hours are never a requirement of a track, and a track with no hours target has no hours condition anywhere,
a certificate rule included; graduates keep the Student Report Card; the Dropped out reason; the mentor-assigned email
and its rules; the Student Duplicate Finder retires with this move; module settings leave wp-admin, and a new screen
belongs on the Administrator Dashboard's frame, while the institutions switch setting stayed in wp-admin by his word;
a field is one field to type and pick; the dashboards look alike and use the shared parts only (`WPCPM_UI`,
`assets/css/parts.css`), with secondary controls kept subtle; "Administrators" is the one name of the role; nothing a
person reads links to Airtable; test mail on the live site goes to one address only; nothing is deleted for good
without his word; every step of a switch is taken on his word on the day.

### 2.2 The twelve still open

Until he answers, the inventory's recommendation is the working assumption. The design marks each place that rests on
one as **(D<n>)**.

| # | Question | Working assumption | If he answers the other way |
|---|---|---|---|
| D1 | Can Administrators work without the Airtable grid? | Yes: a Students view, an enrollment card, a Mentors view and a CSV export (9.4); the frozen base readable for 30 days | Keeping the grid beside the site only holds together with D5's copy: without a copy the grid goes stale at the switch. Both together cost about 8 tasks, which do not fit before 28 December |
| D3 | Who owns the hosting plan, now the program's only record? | The Foundation owns and pays, named before the switch, daily backups kept 30 days, a monthly export checked | Whoever owns it, the day needs a backup taken and restored on the Docker copy the week before (8.5, step 0) |
| D5 | A copy back to Airtable after the switch? | None for students and mentors | A one-way copy of enrollments and mentor records: about 5 tasks, which do not fit before 28 December |
| D6 | The outside readers | One public feed; an in-process read for Student Impact; the WPCredits Tracker reads the feed or retires | A reader left on Airtable is switched off at the switch and shows its last figures (14.7); none reads the frozen base |
| D7 | The Airtable forms | All close on Thursday 24 December, each public link moved to the site's page first | A form left open writes into a base nobody reads: its answers are lost |
| D8 | The emails' texts | The person who owns them pastes the private parts in Tools > Emails and test-sends them to the one test address, by Monday 21 December | Shipped words go out as they are: the guard's condition 10 (8.3) is dropped, and every welcome has no private link |
| D9 | The two emails for one school | Retired | One school-specific template and its trigger: one more task in stream B |
| D10 | Graduation on the site | A Graduate action with a day; codes written per track rule; codes typed in, never in a repository (section 11) | Graduation only today saves half a task; anything else needs section 11 again |
| D12 | Where the post-graduation snapshots live | A private store on the site through an authenticated route (12.4) | A store elsewhere: stream B's two tasks become one, in the script only |
| D15 | Shared Airtable views | Listed, each person told on 21 December, switched off on Thursday 24 December | A share left on shows a frozen base: it must at least be named in the message of 21 December |
| D16 | New tracks during the move | None published from 1 December until the switch is checked | The guard's condition 9 refuses the switch until a fill has read the new track's columns: one more fill run on the day |
| D17 | Privacy | Exporter, eraser and retention with the record; the Privacy Notice updated before the switch | Later tools would mean holding new personal data with no export or erasure: the guard's condition 12 does not let the switch go before them |

## 3. The record

### 3.1 A person, and how the account is found

A person is one WordPress account. The fill and every writer find the account **by the stamped record ID first**
(`wpcpm_student_record_id`, `WPCPM_Students_Sync::META_RECORD_ID`, `modules/class-wpcpm-students-sync.php:74`, which
holds a Students Reports ID today; for a mentor `wpcpm_mentor_record_id`, `WPCPM_Mentors_Sync::META_RECORD_ID`,
`modules/class-wpcpm-mentors-sync.php:89`), **then by the address** (the account's email lowercased against the row's).
Never by the login or the WordPress.org username: those only group rows for an Administrator to look at (6.3).

An account found by its address is put through the students sync's own fence, `adoption_block()`
(`modules/class-wpcpm-students-sync.php:2502`): an account that holds a mentor's stamp, an institution member's stamp
(current or `_was`), or any role but Student or Subscriber is not adopted. **That is the one account case that
blocks** (6.3), never a silent join. Its remedy is the one the sync's own report gives today ("is a mentor's account",
"acts for an institution", `foreign_stamps()`, line 2539): an Administrator gives the student's rows in the base an
address of the student's own, which the person uses for their student account, and the next fill places them there.
The fence's comment says the same: "a program manager settles it by hand".

The fill never joins two accounts, and the design never proposes removing one. Two accounts that look like one person
(the same WordPress.org username, or the same name at one school) are **Good to know**, "Possibly the same person":
each account stays held by its own address, as today. A tool that merges accounts is out of scope.

A person with no account yet (a registration waiting for a decision, a school's import before the invitation) is held
on the enrollment by name, address and username, and is given the account the moment one exists (9.6).

The account keeps `wpcpm_student_record_id` as the key of its current enrollment, or its latest when none is current,
and `wpcpm_mentor_record_id` as its mentor record's key, so every reader that asks "is this a student" or "is this a
mentor" keeps its answer.

### 3.2 An enrollment

One per person per track taken. **At most one is current** (Waiting for a decision, Enrolled, Paused, Pending
graduation). A person may hold any number of closed ones, a retake of the same track after leaving among them. A
private post of the **new** post type `wpcpm_enrollment`, `post_author` the person's account (0 until there is one),
every field in post meta.

**How a Students row pairs with its Students Reports row.** Airtable makes the report row from the Students row (the
automation "Add students to Students Report and Feedback"), and two automations copy status and dates across by
address; there is no reliable link between them. The fill pairs, for each address:

1. the rows whose own link holds it (the Students row's "Students Reports" link names the report row);
2. else by address, then the same status (the state of 3.3, the Students row's Pending graduation counting as the
   report's track), then the same track, then the nearest start date (within 14 days);
3. a Students row left with no report row is an enrollment of its own (it never reached a track); a report row left
   with no Students row is an enrollment of its own, its school, tutor and dates from the report row.

When two rows of one address match a third equally, or step 2 finds more than one answer, the rows **cannot be
paired**: a blocking case, listed by record ID. Two takes of one track by one address tie this way, which is safe. How
many there are is known at the release 1 rehearsal (6.3). A person settles each tie in the base during the shadow
weeks: fills in the Students row's "Students Reports" link to its own report row (the link wins at step 1), or, where
the two takes are one by mistake, corrects the status or the start date of the wrong row.

**What the parity check proves, and what it does not.** It compares each pair's school, tutor and dates with the
roster's (6.2); the roster joins the two tables by address too, so the check proves the schools and dates, **not the
pairing itself**. The one check that does not lean on the address join is the link: for every pair whose Students row
holds a "Students Reports" link, the fill compares the link's row with the row the address join chose, and counts the
pairs that disagree (blocking). Rows with no link have no independent check.

| Field | Meta key (all **new**) | From Airtable |
|---|---|---|
| Key | `_wpcpm_enr_key` | the Students Reports record ID; else the Students record ID |
| Name | `_wpcpm_enr_name` | Students Reports Name; else Students Full Name |
| Email | `_wpcpm_enr_email` | Students Reports Email; else Students Email |
| WordPress.org username | `_wpcpm_enr_username` | Students Reports WordPress Profile; else Students WP Profile |
| Track | `_wpcpm_enr_track` (the track key) and `_wpcpm_enr_status` (the track's status words, "In Sensei") | the Status when it names a track; for any other Status, in order: the account's `wpcpm_student_course`, the Feedback row's Course, Students Requested track; else "Not recorded" |
| State | `_wpcpm_enr_state` | the Status, by the map of 3.3 |
| Current | `_wpcpm_enr_current` (`1`, deleted when closed) | derived |
| Closed from | `_wpcpm_enr_closed_from` | derived: for an imported closed enrollment, Enrolled (Spam: Waiting for a decision) |
| Reason | `_wpcpm_enr_reason` | Students Dropped out reason |
| Institution | `_wpcpm_enr_institution` (an Institutions record ID) | Students Educational Institutions; else Students Reports Educational institution |
| Mentor | `_wpcpm_enr_mentor` (a mentor key) | Students Reports Mentor |
| Tutor | `_wpcpm_enr_tutor` (name and address) | Students Tutor, Tutor email, Tutors official |
| Start, end | `_wpcpm_enr_start`, `_wpcpm_enr_end` (Y-m-d) | Students Start Date, End Date; else Students Reports Internship Start Date, Internship End Date |
| Hours | `_wpcpm_enr_hours` (as typed) | Students Reports Hours |
| Report answers | `_wpcpm_enr_answers` (column name => value) | every Students Reports column a track asks, plus the chat handle column, Personal Website URL, Main Contribution Team |
| Screenshots and files | `_wpcpm_enr_files` (column => list: an image's attachment ID, or a stored file's `path`, `sha256` and `size`) | the 13 attachment columns (7.2), and copies of the account's own `_wpcpm_report_images` |
| Last edited | `_wpcpm_enr_edited` | Students Reports Student report last edited |
| Registration answers | `_wpcpm_enr_registration` | Students Your field of study, Languages, Prefered language, Total hours, English and proactivity acceptance, Privacy Policy Compliance, Requested track, Site import key |
| Accessibility needs | `_wpcpm_enr_access` (its own key, never in a view; release 2, I11) | Students Accessibility needs |
| Graduation | `_wpcpm_enr_graduation` (date, code given on, and how the code is held: 11.2) | Students Reports Graduation date, Certificate code date, and the three certificate code columns |
| Graduates on | `_wpcpm_enr_graduates_on` | none: the Graduate action's day (11.1) |
| After the program | `_wpcpm_enr_alumni` | Students Reports Permanent contact email, Alumni program: personal email, Alumni program: mentoring opt-in |
| Feedback | `_wpcpm_enr_feedback` (3.4) | the Feedback row |
| Help desk link | `_wpcpm_enr_help_desk` | Students, the help desk column |
| Emails sent | `_wpcpm_enr_mailed` (email id => time and `site` or `before`) | written at the switch (10.2) |
| Where it came from | `_wpcpm_enr_airtable` (Students, Students Reports and Feedback IDs), read-only | the three row IDs |
| Fill stamp | `_wpcpm_enr_source` (a hash of the cells it was filled from, and when) | derived |

Not on the record (archived, 7.4): Lessons; Post 5 to Post 8 and Wrap-up WP TV (DELETED); the eleven grade columns;
the three personal link formulas (they point at Airtable forms that close); the Company link; the Contribution Area
text and the test checkbox; the lookups (Mentor email, Mentor's email, the school's Contact Email, the country
Administrator's name and email, Permanent contact email on Students). The Duration formula is computed when shown.

### 3.3 States and the moves between them

One state, one name everywhere. Where the plugin already prints a word for a status (`WPCPM_Program::status_words()`,
`class-wpcpm-program.php:88`: "Not continuing", "Did not complete", "Graduated", "Getting started"), that word is the
state's name on every screen, the school pages included. Every writer that hands a status string to `WPCPM_Program`
gets the same Airtable words as today (`_wpcpm_enr_status` for a current enrollment on its track, the state's Airtable
words otherwise), so `label()`, `badge()`, `tone()` and `hours_text()` answer as they do now.

| State (key) | Its one word | Tone | Current | From Status |
|---|---|---|---|---|
| `registered` | Waiting for a decision | amber | yes | empty (decision 14); Interested (I7) |
| `enrolled` | Enrolled | green | yes | a track status (In Sensei, In Sensei 50h, Developer Track, Designer Track, Foundations Track, Intro to Contribution, the one school's variant, Translator Track); In Sensei Self-onboarding, on the 150-hour track (I7) |
| `paused` | Paused | neutral | yes | Paused |
| `pending` | Pending graduation | amber | yes | Pending graduation |
| `graduated` | Graduated | neutral | no | Graduate |
| `dropped_out` | Dropped out | neutral | no | Dropped out |
| `not_moving_forward` | Not continuing | neutral | no | Not moving forward |
| `failed` | Did not complete | neutral | no | Fail |
| `spam` | Spam | red | no | SPAM |

Duplicated rows are archived and make no enrollment (I7). Any other Status (the Students table's choices also hold a
cohort's name, "Institution 84") is a blocking case if a row holds it.

The allowed moves, and who may make each:

| From | To | Who |
|---|---|---|
| Waiting for a decision | Enrolled; Not continuing; Spam | Administrators |
| Enrolled | Paused; Pending graduation; Graduated (on a day); Dropped out (with a reason); Not continuing; Did not complete | Administrators; the student moves to Pending graduation by sending Feedback form 3 (`WPCPM_Student_Graduation::form_finished()`); a school's representative moves to Dropped out, and to Graduated while `WPCPM_Institution_Students::graduation_offered()` (`modules/class-wpcpm-institution-students.php:151`) says so, as today |
| Paused | Enrolled; Dropped out; Not continuing | Administrators |
| Pending graduation | Graduated (on a day); Enrolled | Administrators |
| A closed state | its `_wpcpm_enr_closed_from` ("Reopen") | Administrators, only while the person has no other current enrollment |

A track may change while an enrollment is Waiting for a decision or Enrolled; answers saved for questions the new track
does not ask stay on the record and are not shown. Starting a new enrollment is refused while one is current, with
"This student already has a current enrollment. Close it first, or change its track."

Every move is one `WPCPM_Enrollment_States::move()` (**new**) under the person's lock (`WPCPM_Lock`, name
`wpcpm_person_<user ID, or a hash of the address>`), which checks the move is allowed from the state it reads under the
lock, writes the state and `_wpcpm_enr_closed_from`, writes the history row, and fires `wpcpm_enrollment_moved`
(**new** action: the key, the state before and after, the actor) for the views, the account and the emails. Every
other change of a field (`WPCPM_Enrollments::patch()`, **new**) fires `wpcpm_enrollment_changed` (**new** action: the
key, the fields changed, each before and after, the actor), which the views, the mentor-assigned email (B23) and the
history read. Both are defined in task A4.

### 3.4 Feedback

Feedback belongs to the enrollment: a second track asks the forms again. `_wpcpm_enr_feedback` holds one entry per form
(`f1`, `f2`, `f3`, `f4`, `f50h`, and `older` for the forms before the website), each the answers by column name as
`WPCPM_Student_Feedback::forms()` names them, the form's `F<n> - Mentor` as a mentor key, and the time sent. The F3
consents stay inside `f3` and keep stamping `wpcpm_report_permissions` as today. Free text answers join the record with
release 2 (I11); the choices and ratings come from release 1.

Airtable keeps one Feedback row per person, its Course frozen when the first track started. The fill places it on the
person's enrollment on the track its Course names (the earliest, if several); with no Course, or a Course no enrollment
holds, on the person's earliest enrollment by start date, then by Airtable's created time.

### 3.5 A mentor

A private post of the **new** post type `wpcpm_mentor_rec`, `post_author` the mentor's account (found as 3.1 says).
One per mentor, never two per account.

| Field | Meta key (all **new**) | From the Mentors table |
|---|---|---|
| Key | `_wpcpm_mrec_key` | the Mentors record ID; a mentor activated on the site after the switch gets a site key (3.8) |
| State | `_wpcpm_mrec_state` (`active` or `inactive`) | Status Active or Inactive (decision 13) |
| Name, email, profile | `_wpcpm_mrec_name`, `_wpcpm_mrec_email`, `_wpcpm_mrec_profile` | Full Name, Email, WordPress profile |
| Directory | `_wpcpm_mrec_country` (a Countries record ID), `_wpcpm_mrec_languages`, `_wpcpm_mrec_expertise` (names) | Country, Languages, Contribution Area - Expertise |
| Sponsorship | `_wpcpm_mrec_sponsorship` | Sponsored, Sponsor company, Sponsor Company Name (the sponsor's site key), Wants to be in the looking for sponsors list |
| Hours | `_wpcpm_mrec_hours` | Available hours per week |
| Application answers | `_wpcpm_mrec_answers` | Teacher from a participating institution?, Anything else, GPL & Trademark, Privacy Policy Compliance |
| Application | `_wpcpm_mrec_application` (a `wpcpm_ment_app` post ID, 0 for none) | matched at the fill (3.6) |
| Help desk link | `_wpcpm_mrec_help_desk` | the help desk column |
| Since | `_wpcpm_mrec_since` | `wpcpm_mentor_activated_at` when set; else the row's created time |
| Where it came from | `_wpcpm_mrec_airtable`, read-only | the Mentors record ID |

Not on the record: "Student Report personal table" and "Password for Student Report personal table" (links to shared
Airtable views and their passwords: neither comes over nor is archived); the link columns (the site derives them); the
four lookups of feedback about the mentor (archived, decision 11). Vetted - positive rows (67) become selected
applications (3.6); Declined (10), Needs more information (5), Dropped out (4) and the one row with no status are
archived (7.4).

### 3.6 An application's link to the mentor

The site's mentor applications (`WPCPM_Mentor_Application`, post type `wpcpm_ment_app`, since 17 October) stay as they
are. A Vetted - positive row becomes a `wpcpm_ment_app` post at `STATE_SELECTED`, its answers from the row, its
selection dated by the row's created time, actor 0, unless an application of the same address already exists
(`WPCPM_Mentor_Application::email_hash()`), which is then left as it is and listed. Activation
(`WPCPM_Mentor_Activation::activate()`, `modules/class-wpcpm-mentor-activation.php:83`) writes the mentor record and its
`_wpcpm_mrec_application` instead of the Airtable row (9.7).

### 3.7 Notes and history

**Administrators' notes** about a person are posts of the **new** post type `wpcpm_person_note`, written by
`WPCPM_Person_Notes` (**new**), the shape of `WPCPM_Institution_Talk_Notes` (`add()` at
`modules/class-wpcpm-institution-talk-notes.php:147`): a key (an enrollment key or a mentor key), the text, the author's
ID or name, a date, a source; newest first, added and never edited. A post type of their own, because their readers,
exporter and eraser are a person's, not a school's. Mentors' own notes about their students stay `wpcpm_mentor_note`
(`WPCPM_Mentor_Notes`), keyed by the enrollment key as today.

**History** reuses `WPCPM_Institution_Audit::record()` (`modules/class-wpcpm-institution-audit.php:209`), which already
keys rows by sponsor, program, lead and tutor: it gains `META_ENROLLMENT` (`_wpcpm_log_enrollment`), `META_MENTOR_REC`
(`_wpcpm_log_mentor_rec`), the ground `GROUND_STUDENT` (`student`) beside `manager`, `member`, `tutor` and `system`
(`grounds()`, line 182), and the kinds `enrollment_moved`, `enrollment_changed`, `enrollment_filled`,
`enrollment_imported`, `mentor_rec_changed`, `people_frozen` and `people_moved` (all **new**;
`bin/test-institution-history.php` pins the count). A row says who, when, which field, from what and to what, and on
what ground.

### 3.8 Keys

- **An enrollment from Airtable keeps its Students Reports record ID** (else its Students ID), because the account's
  `wpcpm_student_record_id`, the mentors' notes and calls, the AI Mentor's transcripts, the told set of
  `WPCPM_Mentor_Assigned` and the REST route `wpcpm/v1/report/<record>` already use it.
- **An enrollment made on the site gets `enr` and 14 letters and digits** (random base-62, the length of an Airtable ID,
  matching the route's pattern `(?P<record>[A-Za-z0-9]+)`, `modules/class-wpcpm-student-report-form.php:788`, which
  takes no hyphen or colon).
- **A mentor from Airtable keeps its Mentors record ID; a mentor activated on the site gets `mnt` and 14.**
- One **new** class owns the shapes: `WPCPM_People_Keys::is_enrollment_key()` (`/^(rec|enr)[A-Za-z0-9]{14}\z/`),
  `::is_mentor_key()` (`/^(rec|mnt)[A-Za-z0-9]{14}\z/`), `::is_site_key()`, `::mint_enrollment()`, `::mint_mentor()`.
  An institution key stays `WPCPM_Airtable::is_record_id()`'s.
- **A key from before resolves.** `WPCPM_Enrollments::find_by_key()` (**new**) answers the enrollment for its key, its
  Students ID or its Students Reports ID, so a bookmark, a roster link or a note that carries a Students ID still opens
  the right enrollment.

### 3.9 Every place that assumes an Airtable record ID for a student or a mentor

The test is `WPCPM_Airtable::is_record_id()` (`class-wpcpm-airtable.php:1227`, pattern `RECORD_ID_PATTERN` at `:113`),
almost always through its kept alias `WPCPM_Mentors_Sync::is_record_id()` (`modules/class-wpcpm-mentors-sync.php:1320`).
Institution keys pass through many of the same files and stay as they are; only the calls below test a student's or a
mentor's key, or read the two account meta keys. Each moves to `WPCPM_People_Keys` or to the reader (5), in the task
named.

**An enrollment key (a Students Reports ID today), or a Students row ID:**

- `class-wpcpm-ability-answers.php`: `mentor()` (mentee IDs, line 845), `roster_pool()` (1201, 1215, 1231),
  `people_matching()` (1272), `mentor_tracks()` (1373, 1377), `tracks_holding()` (1406). Task B9.
- `class-wpcpm-roster-index.php`: the Students row keys of `groups()`, `unlinked_for()`, `write_all()`, `insert()`,
  `update()`, `update_unlinked()`, `same_record()`, `clean_rows()`, `clean()`. Task A12.
- `modules/class-wpcpm-students-sync.php`: `linked_active()`, `phase_provision()`, `settle_owed_step()`,
  `choose_report()`, `provision_student()`, `revoke_departed()`, `user_for_record()` (2717), `find_by_record_id()`,
  `mentor_side_row()`, `apply_report()`. Tasks A9, A29.
- `modules/class-wpcpm-students-dashboard.php`: `is_student()` (207), `all_students()` (405);
  `modules/class-wpcpm-students-table.php`: `search_ids()` (276), `accounts_of_record()` (325);
  `modules/class-wpcpm-student-flags.php`: `rows()` (255). Task B9.
- `modules/class-wpcpm-student-feedback.php`: `kept_form()` (1199). Task A36.
- `modules/class-wpcpm-student-graduation.php`: `rows_of()` (624). Task A44.
- `modules/class-wpcpm-student-report-form.php`: `register_route()` (788), `rest_permission()`, `audience_for()`,
  `rest_report()`, `values()`, `forget()`. Tasks A34, A35.
- `modules/class-wpcpm-mentor-calls.php`: `student_record()` (174, 177), `for_record()` (609);
  `modules/class-wpcpm-mentor-notes.php`: `user_can_access()` (171), `get_notes()` (329), `attended()` (649),
  `focused_student()` (762); `modules/class-wpcpm-tutor-transcripts-view.php`: `render_view()` (106), `render_list()`
  (192), with `WPCPM_Tutor_Store::by_record()`. Task A37.
- `modules/class-wpcpm-mentor-assigned.php`: the told set `OPT_TOLD` (`wpcpm_mentees_told`), mentor key => enrollment
  keys; `stored_lists()` (475). Task B23.
- `modules/class-wpcpm-mentors-dashboard.php`: `get_mentees()` (1069). Task A10.
- `modules/class-wpcpm-institution-roster.php`: `cached_subject()` (315), `claim()` (379), `live_students_row()` (679);
  `modules/class-wpcpm-institution-roster-view.php`: `hidden_fields()` (512);
  `modules/class-wpcpm-institution-students.php`: `render_form()` (449), `handle_change()` (874), `forget()` (1012);
  `modules/class-wpcpm-institution-student-form.php`: `render_form()` (289), `diff_cells()` (787),
  `students_row_for()` (1030). Tasks A38, A39.
- `modules/class-wpcpm-sponsor-members.php`: `attach()` (209) and `modules/class-wpcpm-institution-members.php`:
  `attach()` (327): presence only, unchanged.
- `tools/class-wpcpm-duplicate-finder.php` (`PAIR_PATTERN`, line 48), `tools/class-wpcpm-duplicate-rules.php` (215),
  `tools/class-wpcpm-duplicate-vault.php`: `keep()` (111), `tools/class-wpcpm-duplicate-finder-screen.php`:
  `ref_label()` (991): retired with the Student Duplicate Finder (task B18).
- `modules/class-wpcpm-students.php`: `uninstall()` (188).

**A mentor key:**

- `class-wpcpm-ability-answers.php`: `mentor()` (832, 833), `mentor_account()` (1122, 1127, 1138). Task B9.
- `modules/class-wpcpm-mentor-engagement.php`: `read_accounts()` (163), `holder_of()` (424, 430). Task B9.
- `modules/class-wpcpm-mentor-activation.php`: `activate_held()` (139), `retry_held()` (504), `write_row()` (565),
  `settle()` (651), `settles_as()` (700); `modules/class-wpcpm-mentor-provision.php`: `provision()` (156),
  `confirm_unconfirmed()` (285), `find_by_record_id()` (406), `link_existing_user()` (429, 443), `create_mentor_user()`
  (527); `modules/class-wpcpm-mentor-airtable-row.php`: `other_holder()` (981), `first_record_id()` (1028). Task A42.
- `modules/class-wpcpm-mentor-application-form.php`: `is_mentor_account()` (1837): presence only, unchanged.
- `modules/class-wpcpm-group-sessions.php`: `settled_mentor()` (283); `modules/class-wpcpm-student-questions.php`:
  `mentor_of()` (424); `modules/class-wpcpm-mentor-calls.php`: `mentor_for_student()` (214). Task A37.
- `modules/class-wpcpm-mentor-assigned.php`: `stored_lists()` (475). Task B23.
- `modules/class-wpcpm-mentors-dashboard.php`: `is_mentor()` (852), `all_mentors()` (944). Task A10.
- `modules/class-wpcpm-student-feedback.php`: `handle_save()` (1704, F<n> - Mentor). Task A36.
- `modules/class-wpcpm-students-sync.php`: `foreign_stamps()` (2541), `mentor_user_id()` (3357);
  `modules/class-wpcpm-mentors-sync.php`: `linked_active()` (595), `phase_revoke()` (1869, 1886). Task A30.
- `tools/class-wpcpm-mentor-checker.php`: `ajax_promote()` (457). Task A42.
- `modules/class-wpcpm-mentors.php`: `uninstall()` (224).

Not a key test, left as it is: `WPCPM_Mentor_Directory::listed()` (`modules/class-wpcpm-mentor-directory.php:149`) and
`WPCPM_Mentors_Sync::resolve_stored()` (`modules/class-wpcpm-mentors-sync.php:1337`) resolve link IDs inside cells to
names. A **new** suite, `bin/test-people-keys.php`, reads these files and fails while any of the listed functions still
calls `is_record_id(` on a student or a mentor key, in the manner of the read-off-the-source check in
`bin/test-countries.php`; its list shrinks task by task.

## 4. Storage

**WordPress's own tables, as institutions used.** The site's database connection is latin1 (the 8 October 2026 note,
restated in the institutions design, section 3). A custom table in utf8mb4 written over that connection stores
double-encoded text; WordPress's posts and meta tables are written the way every name the site holds today is
written, and read back the same. The institutions release proved it on the Docker copy with four names in Polish,
Romanian, Arabic and Japanese read back with `HEX()` as single UTF-8 (`Ł` as `C581`, never `C385C281`; its plan, "R2
build and release", step 3). This design repeats that proof with two more cases in the same four scripts: a report
answer and a screenshot's file name.

**Post types (all new, all private).** `wpcpm_enrollment`, `wpcpm_mentor_rec`, `wpcpm_person_note`, with `public`
false, no REST, no UI, `exclude_from_search`, post status `private`, added to `WPCPM_Privacy_Guard::private_post_types()`
(`class-wpcpm-privacy-guard.php:90`; `bin/test-privacy-guard.php` pins the list), removed by `uninstall.php`.

**Size.** About 1,250 enrollments of some 25 meta rows each and 200 mentor records: about 35,000 meta rows. No custom
index is added.

**What each screen runs. No request loads every enrollment's answers.**

| Reader | Query | Index used | Cached, and rebuilt when |
|---|---|---|---|
| Students view, Mentors view, every count, the Overview's queue | none on the record: one read of the list view `wpcpm_people_list` (**new** option, autoload off), one row per enrollment with only the list's fields (key, post ID, person, name, address, username, track, state, institution, mentor key, start, hours) and one per mentor record | the option's primary key | rebuilt for one person on `wpcpm_enrollment_moved` and on each change, under the person's lock; whole by the switch and by `wp wpcredits people_views --rebuild` (**new**) |
| The enrollment card, the Student Report Card, the mentor card | one post and its meta (`get_post_meta()` of one ID, found through the list view's key map) | `wp_posts` primary key; `wp_postmeta` `post_id` | the request |
| An institution's roster | the roster option of that school (`WPCPM_Roster_Index`, `wpcpm_roster_<record ID>`, autoload off today) | the option's primary key | rebuilt for that school on each change of one of its enrollments |
| A mentor's list of students | the mentor's `wpcpm_mentees` user meta | `wp_usermeta` `user_id` | rebuilt for that mentor on each change of one of their enrollments |
| A student's mentor card, program row, course, institution, active flag | the account's meta (`wpcpm_student_program`, `wpcpm_student_mentor`, `wpcpm_student_course`, `wpcpm_student_institution`, `wpcpm_student_active`) | `wp_usermeta` `user_id` | rewritten for that person on each change (task A12, I8) |
| One enrollment by key, outside the list | a meta query on `_wpcpm_enr_key` | `wp_postmeta` `meta_key` (about 1,250 values compared) | the request |
| A person's enrollments | posts by `post_author` | `wp_posts` `post_author` | the request |

**The fill between ticks.** The `record` phases (6.1) never keep rows in the syncs' state options
(`wpcpm_students_state`, `wpcpm_mentors_state`). Each page read is written to a staging option of its own,
`wpcpm_people_stage_<table>_<page>` (**new**, autoload off), the plan is made once every page of the run is in, and the
staging options are deleted when the run ends or is canceled.

**Kept out of autoloaded options.** Every new option is written with autoload off: the list view, the staging pages,
the fill's report (`wpcpm_people_parity`), the file progress (`wpcpm_people_files`), the freeze (`wpcpm_people_freeze`),
the switch's steps (`wpcpm_people_switch_steps`), the sealed certificate codes (`wpcpm_certificate_codes`, sealed with
`WPCPM_Secret::seal_for_option()`). No answer, note or accessibility disclosure is ever kept in an option.

## 5. One reader

**`WPCPM_People`** (**new**) is the one reader of students and mentors, the shape of `WPCPM_Institutions_Index`:
`on_site()` reads the setting `people_on_site` (**new**). Off, each method answers what the copies hold today, so a
caller moved onto it changes nothing. On, each answers from the record and its views. Its methods: `program_row(
$user_id )`, `enrollments_of( $user_id )`, `enrollment( $key )`, `user_for_key( $key )`, `mentor_card( $user_id )`,
`mentees_of( $mentor_user_id )`, `roster_rows( $institution )`, `is_student( $user )`, `is_mentor( $user )`, `mentor(
$key )`, `mentors( $state )`, `counts_by_state()`.

**The old getters become its doors**, so most callers move without being touched: `WPCPM_Students_Sync::get_program()`
(3221), `::get_mentor()` (3583), `::user_for_record()` (2717), `::counts_by_status()` (3534),
`WPCPM_Mentors_Dashboard::get_mentees()` (1069) and `WPCPM_Roster_Index::read()` each ask `WPCPM_People` first.

**The account's own keys stay true in site mode** (I8). Several readers read the account's meta directly and are not
moved; the views write those keys for the person on every move and change (task A12), so they read the record's
answer: `WPCPM_Program::course_status()` (`wpcpm_student_course`); `WPCPM_Institution_Policy::subject_student_account()`
(`modules/class-wpcpm-institution-policy.php:170`, the school's fence, `wpcpm_student_institution`);
`WPCPM_Student_Flags::is_followed()`, `::manager_of()`, `::rows()`, `::calendly_of()`, `::remind()`;
`WPCPM_Students_Dashboard::should_route()` (`wpcpm_student_active`); `WPCPM_Students_Table::institution_to_judge()`;
`WPCPM_Institutions_Dashboard::longest_wait()`; `WPCPM_Institutions::unstamped_students()`;
`WPCPM_Administrators_Cards::graduation()`, `::graduation_owed()`, `::graduation_track()`; `WPCPM_Roster_Index::
unlinked_for()`; `WPCPM_Institution_Export::student_matrix()`, `::row_cells()`; `WPCPM_Institution_Roster_View::
values()`, `::program_of()`, `::mentor_name_of()`; `WPCPM_Institution_Student_Form::current()`; and for mentors
`wpcpm_mentor_active` in `WPCPM_Mentor_Engagement`, `WPCPM_Mentors_Table`, `WPCPM_Mentors_Dashboard::is_mentor()`.

The callers that do the work move one by one, each in a task that keeps the setting off unchanged:

| Today's function | What it reads | Replacement | Task |
|---|---|---|---|
| `WPCPM_Students_Sync::get_program()`, `::get_mentor()`, `::counts_by_status()`; `WPCPM_Mentors_Dashboard::get_mentees()`, `::logged_count()` | the copies | delegate to `WPCPM_People` | A9, A10 |
| `WPCPM_Roster_Index::read()` and the account keys above | the copies | the same options and keys, written by `WPCPM_People_Views` | A11, A12 |
| `WPCPM_Student_Report_Form::values()` (290), `::handle_save()`, `::handle_image_remove()`, `::rest_report()` | the Students Reports row, live; Airtable writes | the enrollment | A34, A35 |
| `WPCPM_Student_Feedback::record_for()`, `::values()`, `::prior_answers()`, `::handle_save()`, `::courses()` | the Feedback row by address, live | `_wpcpm_enr_feedback` | A36 |
| `WPCPM_Mentor_Notes`, `WPCPM_Mentor_Calls`, `WPCPM_Group_Sessions`, `WPCPM_Student_Questions`, `WPCPM_Tutor_Transcripts_View` | key tests | `WPCPM_People_Keys` | A37 |
| `WPCPM_Mentor_Engagement`, `WPCPM_Ability_Answers`, `WPCPM_Student_Flags`, the wp-admin Students dashboard and table (`WPCPM_Students_Dashboard`, `WPCPM_Students_Table`) | key tests, the copies | `WPCPM_People_Keys`, `WPCPM_People` | B9 |
| `WPCPM_Institution_Roster::claim()`, `::live_students_row()`, `::students_record()`, `::students_row_for_email()`; `WPCPM_Institution_Roster_View`; `WPCPM_Institution_Export` | the Students and Students Reports rows, live | the enrollment and the roster view | A38 |
| `WPCPM_Institution_Student_Form::write_cells()`; `WPCPM_Institution_Students::handle_change()`; `WPCPM_Institutions::handle_link()`, `::reports_by_email()` | Airtable writes | `WPCPM_Enrollments`, `WPCPM_Enrollment_States::move()` | A39 |
| `WPCPM_Institution_Create::run_rows()`, `::find_created()`; `WPCPM_Institution_Import::check_against_base()`, `::lookup_by_handle()`, `::lookup_by_email()` | the Students table; `create_records()` | new enrollments at Enrolled on the offered track | A40 |
| `WPCPM_Student_Registration_Process::settle()`; `WPCPM_Student_Airtable_Row` | the Students table; Languages | a new enrollment at Waiting for a decision | A41 |
| `WPCPM_Mentor_Activation`, `WPCPM_Mentor_Provision`, `WPCPM_Mentor_Airtable_Row`; `WPCPM_Mentor_Checker_Runner::get_mentors_to_check()`, `::read_mentor()`, `::write_status()`, `::promote()` | the Mentors table | a mentor record; the checker's `site_rows()` (`tools/class-wpcpm-mentor-checker-runner.php:87`) with `checker_skip_airtable` on | A42 |
| `WPCPM_Student_Graduation::finish()`, `::attempt()`, `::retry()`, `::on_track()`, `::rows_of()` | the Students row, live | `WPCPM_Enrollment_States::move()` | A44 |
| `WPCPM_Outcome_Figures::refresh()`; `WPCPM_Semester_Report::read_reports()`, `::read_feedback()` | Students Reports, Feedback, live | the record | B10 |
| `WPCPM_Track_Publish::preflight()`, `::run()`, `::verify()` | the base's schema; `create_field()` | no Airtable step in site mode | A29 |
| `WPCPM_Students_Sync`, `WPCPM_Mentors_Sync` (every entry), `WPCPM_Duplicates_Scan` | everything | gated in site mode (8.7); their code stays 30 days | A29 |

## 6. Filling the record before the switch

### 6.1 The shadow fill

The record is filled from Airtable at every sync run from release 1, with the setting off, so three weeks of runs
(from Tuesday 8 December) find the fill's problems while Airtable is still in charge. No screen reads the record until
the setting is on.

The existing phases cannot carry it: the students sync reads Students Reports only at the tracked statuses
(`formula_in( report_status, $statuses['all'] )` in `phase_reports()`, `modules/class-wpcpm-students-sync.php:887`), so
SPAM, Fail and no-status rows never reach it, and it asks for none of the report answers. So each sync gains one **new**
phase, `record`, after its last one:

- **Students sync:** pages Students, Students Reports and Feedback, every row, the record's columns (3.2, 3.4) by name,
  each page into its staging option (section 4). About 13 pages per table, within the run's budget (`BUDGET` 18 seconds
  a tick).
- **Mentors sync:** pages the Mentors table, every row, the record's columns (3.5).
- Both run only while `people_shadow` (**new** setting, Settings > Advanced, off by default, turned on after release 1
  on the product owner's word) is on.
- `wp wpcredits people_fill` (**new**) runs both `record` phases to the end in one process, for the day (8.5) and the
  rehearsals: a few minutes, not a sync's three-hour slot.

`WPCPM_People_Fill::plan( $rows )` (**new**) is a pure function: it finds each person (3.1), pairs the rows (3.2), maps
them to enrollments and mentor records, and lists its problems. `::apply( $plan )` writes the record by the hash in
`_wpcpm_enr_source` (an unchanged row costs no write), never sends an email, never writes to Airtable and never creates
an account.

**A row gone from the base is listed, never removed by the fill** (decision list, 30). A fill counts in the manner of
`WPCPM_Sync_Guard::check()` (`modules/class-wpcpm-sync-guard.php:47`): a page that failed, a read that came back short,
or a table read with fewer than half the rows the record holds from it, is an incomplete fill, which places nothing as
gone. After a complete fill, a row the record holds and the base no longer does, or whose cells were erased there, is
listed in the report as "Gone from Airtable" (Good to know) and stays on the site. It leaves the site's record only on
an Administrator's command, `wp wpcredits people_fill --remove=<key>` (**new**, task A15), which gives the enrollment,
mentor record or note the post status `wpcpm_removed` (**new**, registered, private, never emptied by WordPress's
trash schedule), so nothing is deleted for good without the product owner's word.

**The most personal fields wait for the privacy tools** (I11): release 1's fill leaves out accessibility needs, feedback
free text and the Administrators' notes and record comments; they join the fill with release 2, which ships the
exporter and the eraser (task B7, B8), and are read from then on.

### 6.2 The report of differences

After each fill, `WPCPM_People_Parity` (**new**) keeps in `wpcpm_people_parity`:

1. **The fill's problems** (6.3), each row with its case, its Airtable record IDs, and whether it blocks the switch.
2. **The reader's two answers.** For every student account, every mentor account and every institution: what
   `WPCPM_People` answers with the setting off (the copies the syncs wrote) against what it would answer with it on,
   field by field: the program row's name, address, status, track, start, end, institution, hours, mentor; the mentor
   card; each mentor's list (the same keys, the same past flags); each institution's roster rows, with each pair's
   school, tutor and dates (C4); the counts by state. Any difference blocks.
3. **Files:** listed and stored, per column.

An Administrator reads it on the Watch view's card "Students and mentors move" (9.4) and with `wp wpcredits
people_parity` (**new**). "Good to know" rows do not block: each is listed so an Administrator can fix it in Airtable
before the switch or on the site after it.

### 6.3 The cases

| Case (counts of 20 November) | What the fill does | Blocks |
|---|---|---|
| One address on several Students Reports rows (59 addresses) | One person; each row its own enrollment, in start order | Only when two are current |
| Rows of one address that cannot be paired (3.2) | Not placed; listed by record ID | Yes |
| Two rows of one address on one track, one current and one closed | Two enrollments, the closed one a past take | No; Good to know |
| Two current rows of one address on one track | Neither placed (merging answers is the anti-goal) | Yes |
| An account found by address that `adoption_block()` refuses | Not placed: "The account of this address is a mentor's, a school member's, or holds another role. Give the student's rows an address of their own." | Yes: the one account case that blocks |
| Two accounts that look like one person (same WordPress.org username, or same name at one school) | Not joined; each held by its own address: "Possibly the same person" | No; Good to know |
| One address with two WordPress.org usernames on its rows | Placed by the address; listed | No; Good to know |
| No account for the address | Held by address until an account exists | No |
| Feedback row with no Course (609) | On the person's earliest enrollment (3.4) | No; counted |
| Feedback row whose address has no student row | Archived (7.4) | No; listed |
| A link to a row that no longer exists (5 broken pointers) | The field left empty, the old ID kept in `_wpcpm_enr_airtable` | No; listed |
| The two student tables disagree on Status | The Students Reports Status governs, except Pending graduation, where the Students Status governs (`WPCPM_Students_Sync::META_PENDING`) | Only when one says current and the other closed |
| A Status no state holds (3.3) | Not placed | Yes |
| Duplicated | Archived, no enrollment | No; counted |
| A closed enrollment whose track cannot be found | Track "Not recorded"; drawn with the 150-hour form, as `WPCPM_Student_Report_Form::fields()` does today for no track | No; counted |
| A Mentors row at Vetted - positive whose address has an application | The application left as it is | No; listed |
| A row gone from the base, or erased there, after a complete fill | Listed as "Gone from Airtable"; leaves only on an Administrator's command, to `wpcpm_removed` (6.1) | No; Good to know |
| A certificate code on a row that differs from its track's typed code | Kept as 11.2 says | No; listed |

**What the release 1 rehearsal counts to prove the pairing rule on the real rows** (counts only, no names): Students
rows; Students Reports rows; pairs made by the link, by address and status, by track, by date; Students rows with no
report row; report rows with no Students row; rows that cannot be paired; and, per pair, how many differ from the
roster's school, tutor or dates; and the pairs whose link disagrees with the address join (3.2). The rule is proven when
"cannot be paired", "differ" and "disagree" are all zero, or each is a row an Administrator can fix in the base; the
ties among them are known from this count.

## 7. The history that comes over

Everything comes over during the shadow weeks and is kept current by the fill, so nothing slow runs on the day (C2).
What must be read one record at a time, or once, is brought by one **new** command,
`wp wpcredits people_import [--dry-run] [--since-last] [--skip-comments] [--files] [--save=<file>] [--from=<file>]
[--archive=<file>] [--yes]`, in `WPCPM_People_Move` (**new**), the shape of `WPCPM_Institutions_Move` (`ready()` at
`modules/class-wpcpm-institutions-move.php:139`): a run that writes requires a fill no older than 30 minutes, refuses
once the setting is on, writes nothing on a dry run, saves a read only outside a repository and outside the site (the rule of
`WPCPM_Institutions_Move::save()`), and run twice ends where one run ends. **A saved read has no freshness rule:** `--dry-run --save=<file>` writes
nothing on the site and reads without asking for a fill, since the saved read carries its own time (`read`), and
`--from=<file>` on a rehearsal copy takes it whatever its age, as `WPCPM_Institutions_Move::ready()` takes a saved read
on a copy whose environment is `local` or `development` (its `$saved` branch, lines 147 to 150, with `rehearsal()` at
:171). This departs from the institutions' rule for a fresh `--save` (line 159 asks for the sync there), because this
dry run writes nothing and is what the release 1 rehearsal needs while `people_shadow` is still off (14.4). It runs
first during the shadow weeks (whole,
about an hour with the record comments), then each week with `--since-last` (only rows changed since its last run, by
the fill's hashes), and on the day with `--since-last` or `--from=<the dry run's save>`, a few minutes.

### 7.1 Notes and record comments (from release 2, I11)

The Notes cell of each Students and Mentors row becomes the first note, dated the import day, "Imported from Airtable",
source `airtable-notes:<rec>`; a long text becomes several notes in order (`:1`, `:2`), as
`WPCPM_Institutions_Move::pieces()` does. Each record comment on a Students, Students Reports, Feedback or Mentors row
becomes a note with its own date and author's name, source `airtable-comment:<id>` (the comment's own ID). A comment
changes no cell, so `--since-last` cannot see one added later: the comments are read whole once more on the day,
**after** the switch, from the base read-only since the sitting began (8.5, step 7b), and the comment's ID keeps that
pass from adding anything twice. A note is written only when its
source is not on the site (`WPCPM_Person_Notes::has_source()`, **new**). A Notes cell changed in the base after its
note was written adds a new note, "Changed in Airtable", so nothing is overwritten.

### 7.2 Screenshots

Thirteen Students Reports columns hold files. Airtable's links expire within hours, so a file is fetched in the tick
whose read gave its link, never from a saved read.

- **When:** by every fill from release 1; the day's last fill fetches only what is new.
- **Where:** an image through `WPCPM_Image_Upload::store()` (`class-wpcpm-image-upload.php:164`) with `private` and
  `generated_name` on, author the student's account (0 until there is one), its attachment ID kept; any other file
  through `WPCPM_Private_Files::store()` (encrypted at rest), which returns the stored `path`, `sha256` and `size`, kept
  as such. The Airtable attachment ID is the key, so a file is fetched once.
- **The site's own screenshots** (`_wpcpm_report_images`, per account by column) are copied onto the enrollment they
  belong to (the current one at the time of the fill), and **left in place on the account** while the setting is off
  (I12). They are removed from the account only after the switch, on the product owner's word.
- **Counted:** listed and stored per column, failures with why, in `wpcpm_people_files`; a failed fetch is tried again
  at the next fill with a fresh link.

### 7.3 Applications and the told set

The Vetted - positive rows become selected applications (3.6). `WPCPM_Mentor_Assigned`'s told set keeps its keys
(mentor keys => enrollment keys), the same IDs.

### 7.4 The private archive

`people_import --archive=<file>` writes, once, during the shadow weeks: every Lessons row (all 61 columns, paged to the
end, which settles whether 2,001 is a cap), the DELETED columns, the eleven grade columns, the mentors' four feedback
lookups, the Tutors table's two phone columns, the archived Mentors rows, the Duplicated rows, Feedback rows no student
row holds, and (D12) the Post-Grad Snapshots table. One JSON document per table and a manifest of counts and checksums,
with each Lessons file inside as its name, type, size and bytes in base64 (parts above 200 MB, `--part=<n>`). It
refuses a path inside a repository or inside the site, writes for its owner alone, runs on the live site on the product
owner's word, piped over ssh into the program's private folder, and is committed nowhere.

## 8. The switch

### 8.1 The setting and its one change path

**`people_on_site`** (**new**), off by default, in `scopes()['advanced']`, drawn on Settings > Advanced beside
"Institutions' record" (`render_tab_advanced()`, `class-wpcpm-settings-screen.php:2757`). Label "Students' and mentors'
record"; the box "The site keeps the record"; its description "Turned on with wp wpcredits people_switch on the day of
the move. From then both syncs stop reading Airtable, every change is made on the site, and the site sends the
students' and mentors' emails."; the off hint "Off, the syncs read Airtable again and rewrite the site's copies."

**Turning it on is a command, in steps** (decision list, 29): `wp wpcredits people_switch [--dry-run] [--abandon]
[--yes]` (**new**), in `WPCPM_People_Switch` (**new**), the shape of `WPCPM_Institutions_Switch::change()`
(`modules/class-wpcpm-institutions-switch.php:47`). A save of the box that ticks it is refused with "Turn the
students' and mentors' record on with wp wpcredits people_switch: it runs in steps that must be able to resume." Turning
it off goes through the box or `wp wpcredits setting set people_on_site 0`, both through `WPCPM_People_Switch::off()`
(**new**), which also does 8.9's safety.

### 8.2 The freeze

**`people_freeze`** (**new**, kept in `wpcpm_people_freeze`: since, until, who), set by `wp wpcredits people_freeze on
--until="<date time UTC>"` and lifted by `people_freeze off`, by the switch's last step, or by `people_switch
--abandon`. **It never lifts by itself at its until time**: the until time is what people are told, and only one of
those three commands ends it (decision list, 32). While it holds:

- every writer that writes a student's or a mentor's row in Airtable refuses before it writes (the list below);
- the syncs do not run: `WPCPM_Students_Sync::cron_auto()`, `WPCPM_Mentors_Sync::cron_daily()` and every
  `::run_tick()` return before they read, and `::start()` returns `WP_Error` `wpcpm_people_frozen`. The Sync tabs
  (`WPCPM_Sync_Module`) draw no "Sync now" and say "The record is frozen for the move since %s, so this sync does not
  run."; `wp wpcredits sync_mentors` prints the same and exits with an error. `wp wpcredits people_fill` is the one
  reader that runs, for the sitting.
- **The freeze never begins over a sync in flight.** A run that started before it would never finish while frozen,
  since `is_running()` stays true for a run whose phase is not `done` (`modules/class-wpcpm-students-sync.php:455`; the
  mentors sync's the same, line 522), and guard condition 3 would then refuse for good. So `people_freeze on` refuses
  while either sync is running, and never cancels one: "The record was not frozen: the %s sync is running. Wait until it
  finishes (its last run took %s, on its Sync tab), then run the command again." A run due once the freeze holds does
  not start (`cron_auto()` and `cron_daily()` return first).

**The sentence a refused save shows:**

"The program's records are being moved to this site. Saving is paused until %s. What you typed is kept, so you can
save it then."

**How the time is written, so nobody reads it as their own clock.** A student has no time zone of their own on the site:
`WPCPM_Mentor_Availability::has_timezone()` (`modules/class-wpcpm-mentor-availability.php:369`) is answered by a setting
mentors make. So the time is written with its weekday, date and hour and "UTC" said: "Monday 28 December, 02:00 UTC".
An account that has chosen a zone (mostly mentors) reads it the way the call emails write a time (`mail/templates/calls.php`):
`wp_date()` in `WPCPM_Mentor_Availability::viewer_timezone()` (`:354`), the zone named by `::zone_label()` (`:754`), then
the UTC time after it: "Sunday 27 December, 23:00 (America / Sao Paulo), which is Monday 28 December, 02:00 UTC". No
script on the Student Report Card, the feedback forms or the Institution Dashboard shows a time on the reader's clock
today (the one browser-zone script, `assets/js/calendar.js`, only offers a zone on the mentor's calendar), so none is
added for two hours.

The until time is the planned end of the frozen part (01:10 UTC) plus a margin of 50 minutes: **Monday 28 December,
02:00 UTC**. If the sitting runs past it, `people_freeze on --until=` moves it once, by at most 30 minutes; past that,
the sitting abandons (8.4).

What was typed is kept by each form's own keeping, as for any refusal: the Student Report Card's
(`WPCPM_Student_Report_Form::keep()`, `KEPT_FORM`), the feedback forms' (`WPCPM_Student_Feedback::kept_form()`) and
`WPCPM_Form_Stash` for the public forms; a form that keeps nothing on a refusal today (the school's student and status
forms) gains the same keeping in task A28. Reading goes on everywhere.

**The writers, and what each does while frozen** (task A28):

- `WPCPM_Student_Report_Form::handle_save()`, `::handle_image_remove()`; `WPCPM_Student_Feedback::handle_save()`;
  `WPCPM_Institution_Student_Form`, `WPCPM_Institution_Students::handle_change()`, `WPCPM_Institutions::handle_link()`:
  refuse with the sentence, keep what was typed.
- `WPCPM_Institution_Create` (a school's import): a batch is not started (the sentence); nothing is owed, since the
  sitting began only once none was unfinished (8.5, step 0).
- `WPCPM_Mentor_Activation::activate()` and the manual retry "Write the Airtable row again"
  (`::retry_airtable()`, `modules/class-wpcpm-mentor-activation.php:452`): refuse with the sentence; the application
  stays selected.
- The Student Duplicate Finder's delete (`WPCPM_Duplicate_Delete::run()`, `tools/class-wpcpm-duplicate-delete.php:66`,
  behind `duplicate_delete_enabled`): refused with the sentence, nothing sent.
- The command line's check row (`WPCPM_Mentor_Airtable_Row::check_row()`, `modules/class-wpcpm-mentor-airtable-row.php:1104`,
  `wp wpcredits mentor_airtable_row`): prints the sentence and writes nothing.
- The students sync's graduation retry (`settle_owed_step()` calling `WPCPM_Student_Graduation::retry()`,
  `modules/class-wpcpm-students-sync.php:1634`): does not run, since no tick runs; `retry()` itself refuses while frozen
  as a second guard.
- `WPCPM_Student_Graduation::form_finished()`: Feedback form 3 is saved on the site as today and its Pending graduation
  is kept, set on the record by the switch's step 2 (or written to the base by `form_finished()` again on an abandon).
- The Mentor Status Checker's promotion: its cron skips while frozen.
- The public registration form keeps accepting: a registration is stored on the site as today and waits;
  `WPCPM_Student_Registration_Process::settle()` and its hourly retry skip while frozen, and the switch's step 2 makes it
  an enrollment waiting for a decision.

**What each person sees during it:**

- **A student:** the Student Report Card and the feedback forms as always; Save answers the sentence and keeps the
  words; a line at the top of the card, "The program's records are being moved to this site. You can read everything;
  saving comes back by %s." (the same time, written the same way). Registering works as normal.
- **A mentor:** the Mentor Report Card as always; notes, calls and availability keep working (they were always the
  site's).
- **A school:** the Institution Dashboard reads as always; adding students, changing a status and editing a student
  answer the sentence.
- **An Administrator:** the same refusals; the Sync tabs' line above; the Watch card shows the freeze and the sitting's
  steps with their stamps.

A history row `people_frozen` is written when the freeze starts and when it ends.

### 8.3 The guard

`WPCPM_People::may_turn_on()` (**new**) asks, in this order, and refuses with the first sentence that applies. Each
sentence begins "The students' and mentors' record was not moved:". Every condition can be met in one sitting: none
waits for a schedule.

1. `institutions_on_site` is on. "... the institutions' record is still Airtable's. Move it first, then run the command
   again."
2. The freeze holds. "... the record is not frozen. Run wp wpcredits people_freeze on first."
3. Neither sync is running (`WPCPM_Students_Sync::is_running()`, `WPCPM_Mentors_Sync::is_running()`). It holds by
   construction, since the freeze began only once neither was (8.2) and no run starts while frozen; it stays as a
   guard. "... a students or mentors sync is running. Wait until it finishes, then run the command again."
4. **Nothing changed since the fill read it.** Two complete fills finished after the freeze began, and the second
   changed nothing (every row's hash equal); no Students Reports row's "Student report last edited" (the base's own
   last-modified field, `lastModifiedTime` in `bin/fixtures/reports-table-fields.json`) is later than the second fill's
   read. Students, Feedback and Mentors have no last-modified field, so **the switch's step 1 runs one more fill itself,
   comparing only**, and refuses if any row's hash moved since the second: that closes the gap between the last fill
   and the switch. "... a row changed in Airtable after the last fill. Find it on the Watch card, then run wp wpcredits
   people_fill twice and this command again."
5. The report of differences has nothing that blocks. "... the report of differences has %d rows that block the move.
   Read them on the Administrator Dashboard, under Watch, then run the command again."
6. Nothing was owed to Airtable when the freeze began (it cannot be settled while frozen, so the sitting checks it
   first, 8.5 step 0). "... the site owed Airtable %s when the freeze began. Run wp wpcredits people_freeze off, settle
   them as wp wpcredits people_owed says, and start the sitting again." It counts registrations at
   `WPCPM_Student_Registration::STATE_WAITING` or `STATE_FAILED` older than the freeze, mentor rows owed
   (`wpcpm_mentor_row_pending`), graduations owed (`WPCPM_Student_Graduation::owing()`), school import batches
   unfinished (`wpcpm_import_batch`).
7. The history is current: `people_import` finished after the last fill began, and every file the fill listed is
   stored. "... the history is not current, or %d screenshots are not stored. Run wp wpcredits people_import
   --since-last, and wp wpcredits people_fill for the screenshots, then run the command again."
8. The operator confirms in the command that the 27 automations are off and the base read-only for everyone but its
   owner, and that the forms and shares were closed on 24 December (`--yes`, after the command prints the list of
   8.6). "... the Airtable steps of the day are not confirmed. Read the list above, and run the command again with
   --yes once each is done."
9. The report answers of every published track were in the last fill (D16). "... the track %s was published after the
   last fill. Run wp wpcredits people_fill, then run the command again."
10. Every email of the Enrollments area (section 10) has an edit saved in Tools > Emails, for every track with a current
    enrollment (`WPCPM_Mail_Templates::edit()`, `mail/class-wpcpm-mail-templates.php:680`) (D8). "... %s has not been
    read and saved in Tools > Emails, so it would go out with its shipped words. Save it there, then run the command
    again."
11. Every track with a certificate rule has its code typed (D10). "... the certificate code of %s is not typed in. Type
    it with wp wpcredits certificate_code, then run the command again."
12. The privacy exporter and eraser are on, and the Privacy Notice page (`wp_page_for_privacy_policy`) was saved after
    release 2 (D17). "... the Privacy Notice has not been updated since the site began keeping students' records.
    Update it, then run the command again." (A check that someone looked, not that the words are right.)

**`wp wpcredits people_owed`** (**new**, task A26) prints what conditions 6 counts, each with what to do while not
frozen: a registration waiting or failed, "write it now: Students > Registrations, Write it now, or wp wpcredits
people_owed --write"; the tab's existing press, "Write it now" (`WPCPM_Student_Registrations_Tab::handle_write()`,
`modules/class-wpcpm-student-registrations-tab.php:177`), tries a failed or a held registration once at once, and the
**new** `--write` (task A26) does the same through `WPCPM_Student_Registration_Process::process()` for every waiting or
failed one, a plain waiting one included, so nobody waits an hour for the retry;
a mentor row owed, "the application's page: Write the Airtable row again"; a graduation owed, "the Students waiting to
graduate card: the row says what the base lacks"; an unfinished school batch, "the school's import page: let it finish
or cancel it". It also prints whether either sync is running and when each is next due (`wp_next_scheduled()` of
`wpcpm_students_daily` and `wpcpm_mentors_daily`). It runs in the dry run of Thursday 24 December (the working day
before the day, since 25 December is not one) and at the sitting's step 0.

### 8.4 What turning on does, in steps

`people_switch` runs these steps in order, stamping each in `wpcpm_people_switch_steps`; run again, it resumes at the
first step not stamped. **The setting is written last**, so until step 7 the site is still in Airtable mode, frozen.

| Step | Does | If it fails or is refused, the site is |
|---|---|---|
| 1 | Runs one more fill, comparing only (condition 4), then asks the guard (8.3) | frozen, in Airtable mode, nothing changed |
| 2 | Makes each registration that arrived during the freeze an enrollment at Waiting for a decision, and each kept Feedback form 3 send a Pending graduation, on the record. **Idempotent per registration and per send**: each is stamped with the enrollment it made, and a resumed step 2 makes none twice | frozen; the record has them, nothing reads it yet |
| 3 | Stamps every enrollment's emails as already told (10.2) | frozen; the stamps are inert in Airtable mode |
| 4 | Rebuilds every view and account key from the record (`WPCPM_People_Views::rebuild_all()`) | frozen; the views equal the copies, since the report is empty, so pages read the same |
| 5 | Sets each account's `wpcpm_student_record_id` to its current or latest enrollment key | frozen; the keys are the same Students Reports IDs for imported enrollments |
| 6 | Keeps the earlier value of `checker_skip_airtable` in the steps **before** it writes it on; unschedules `wpcpm_duplicates_scan` | frozen; `--abandon` restores both |
| 7 | Writes `people_on_site` and the history row `people_moved` | in site mode |
| 8 | Sends "New student registered" (`enrollment-registered`) once for each registration of step 2 (stamped on its enrollment), then lifts the freeze; those registrations are on the Overview's queue | in site mode, open |

The site keeps the record from the moment step 7 writes the setting.

**A save of Settings > Advanced drawn before step 7 does not turn the record off.** The tab carries the value it was
drawn with (`WPCPM_Settings::FIELD_DRAWN`, as `record_switch()` reads it for `institutions_on_site`,
`class-wpcpm-settings-screen.php:551`), and a save whose drawn value for `people_on_site` was off, while the stored value
is now on, leaves it on and says "The students' and mentors' record was turned on after this page was drawn, so it was
left on. To turn it off, open the tab again."

**Abandoning.** Allowed only before step 7 is stamped; once the setting is written, `--abandon` is refused with "The
site keeps the record now. To go back, untick the setting: see the way back.", and going back is 8.9. First, by hand,
on the product owner's word: the automations back on (only those that were on) and the base's write access given
back, so no status change made after the freeze lifts goes without its Airtable email. Then `people_switch --abandon`
undoes steps 6 to 2 in reverse: it restores `checker_skip_airtable` and the scan; **removes the enrollments step 2
made** (and their stamps), so the next fill cannot make a second current enrollment for the same person, and puts each
of those registrations back to waiting so the hourly retry writes it to the base; hands each kept Feedback form 3 send
to `WPCPM_Student_Graduation::form_finished()` again, which writes the base as today; and lifts the freeze. The step 3
stamps stay, inert. The forms and shares closed on 24 December stay closed: the site's forms and cards serve those
people already. If it is abandoned, the product owner sends a short message to the Administrators, mentors and
schools: "The move of the program's records is postponed. Everything works as it did before; what you could not save,
save now."

### 8.5 The day: one sitting from Monday 28 December, 00:00 UTC

One sitting. Nothing live needs anyone before 00:00 UTC, the hour the product owner set for the institutions switch,
when it is night in Europe and the evening of 27 December in the Americas, and fewest students save. Every live step is
on the product owner's word, given at that step.

**Before the day, nothing frozen:**

| When | What | Who |
|---|---|---|
| By Monday 21 December | The email texts saved and test-sent (D8, condition 10); the certificate codes typed (`certificate_code`, condition 11); the Privacy Notice updated (condition 12) | the person who owns the texts; an Administrator |
| Monday 21 December | A backup of the live site taken and restored on the Docker copy (D3); the message of 14.8 sent | the controller; the product owner |
| Thursday 24 December | The Airtable forms closed (D7) and the shared views switched off (D15), 30 minutes; the readers that slipped switched off (14.7) | a person with owner rights on the base; the controller for the readers |
| Thursday 24 December | **The syncs' minutes.** Both run every three hours at fixed minutes of a cycle set when the students sync was first scheduled (`schedule()` and `cycle_start()`, `modules/class-wpcpm-students-sync.php:280` and `:319`: the students sync at the cycle's +0:30, the mentors sync at +1:00, the institutions sync at +1:30, `SCHEDULE_OFFSET_MINUTES`). `people_owed` prints their next times; from them the controller writes down the UTC minutes each fires on 28 December. If either is due by 00:05 on 28 December and less than its last run's length before 00:00 (so it could still be running at 00:00), the sitting's start moves to ten minutes after that run's usual end, decided that day with the product owner | the controller |
| Thursday 24 December | A dry run of the whole list on live, read-only towards Airtable: `people_owed`, `people_fill`, `people_import --since-last --dry-run`, `people_parity`, `people_switch --dry-run` (every condition but the freeze's, the fills' and `--yes` answered), timed | the controller, read with the product owner |
| Thursday 24 December | **The copy's write test** (8.8): after the base is made read-only on a test basis for one collaborator, one harmless write through the institutions copy's own path (`WPCPM_Institution_Copy::send()`) to the test institution's row, then put back. If it is refused, the site's token is not the owner's: on the day, the base is made read-only for every collaborator but the owner **and** the site's token holder, and condition 8's confirmation says so | the controller and a person with owner rights on the base |

Closing the forms and shares three days early loses nothing: every public link points at the site's own form since D7
(the site's registration form, mentor application and feedback forms on the Student Report Card), a closed Airtable form
says it is closed and names the site's page in plain words, and a mentor or a school that opens a shared view finds it
gone and has read since 21 December where their students are now (the Mentor Report Card, the Institution Dashboard).

**The sitting (UTC):**

| UTC | Step | Who | About |
|---|---|---|---|
| 00:00 | 0. The product owner's go; `wp wpcredits people_owed` says nothing is owed and neither sync is running. Anything owed is written now (`--write`, Write the Airtable row again), before any freeze, or the sitting stops here. A sync running is waited for (its last run's length, on its Sync tab); the 24 December reading of the minutes is what keeps that from happening | the controller | 5 minutes |
| 00:05 | 1. `wp wpcredits people_freeze on --until="2026-12-28 02:00"`. The freeze begins | the controller | 1 minute |
| 00:06 | 2. The 27 automations off (8.6), each confirmed undeployed | a person with owner rights on the base, or the controller through the connector on the product owner's word at that step (as the six institutions automations are planned) | 15 minutes |
| 00:06 | 2b. Beside it: the base read-only for everyone but its owner (and the site's token holder, if 24 December's test says so) (I14) | a person with owner rights on the base | 10 minutes |
| 00:21 | 3. `wp wpcredits people_fill`, twice | the controller | 10 minutes |
| 00:31 | 4. `people_import --since-last --dry-run`, read; then without `--dry-run` | the controller, with the product owner | 10 minutes |
| 00:41 | 5. `people_parity` read; `people_switch --dry-run` | the controller, with the product owner | 8 minutes |
| 00:49 | **Hold point:** go, or abandon with nothing to undo but the freeze and steps 2 and 2b | the product owner | 3 minutes |
| 00:52 | 6. `wp wpcredits people_switch --yes` (its step 1 runs one more fill, comparing). The site keeps the record from the moment its step 7 writes the setting; its step 8 sends the freeze's registration notices and lifts the freeze | the controller | 15 minutes |
| 01:07 | 7. The checks of the day (16.3) | the controller, with the product owner | 50 minutes |
| 01:07 | 7b. Beside it, outside the freeze: the record comments read whole once more (`people_import --comments-only`, **new** option), from the base that has been read-only since 00:06, so nobody can add one meanwhile; keyed by each comment's own ID, so nothing is added twice. About 3,900 one-record requests (1,254 Students, 1,236 Students Reports, 1,238 Feedback, 200 Mentors), about 20 minutes at the client's pace. The freeze does not wait for it | the controller | 20 minutes |
| 01:57 | Done, once 7b's count is read with the product owner | | |

**The freeze's length.** In the good case it lifts at the switch's step 8, about **01:07 UTC: about one hour**, shown
to people as "until Monday 28 December, 02:00 UTC" (the planned frozen part to 01:10, plus 50 minutes). Abandoned at
the hold point, it lifts once steps 2 and 2b are undone and `--abandon` has run, about **01:15 UTC**. Either way under
two hours. The product owner's part of the sitting is about two hours, 00:00 to 01:57 UTC.

### 8.6 The Airtable automations to switch off

The 27 deployed, by what they do (inventory 3): adding a student's Students Reports and Feedback rows when a Students
row reaches a track; copying status and dates from Students to Students Reports; the test back-fill; copying each of
Feedback forms 1, 2 and 3 into the Feedback row; setting Pending graduation and emailing the program inbox when form 3
is sent in Airtable; setting both tables to Graduate on the graduation date; the certificate notice; the three
certificate code writers; the eight track welcomes (In Sensei, 50h, Developer, Design, Foundations, Intro to
Contribution, the one school's variant, Translation); the Not moving forward, Dropped out and Withdrawal notices; the
new student registered notice to one Administrator; copying the school's contact email into the tutor email; the two
emails for one school (D9). The ten undeployed ones stay undeployed. None is deleted.

### 8.7 The syncs in site mode

Gated at every entry, not only at the cron (task A29):

- `WPCPM_Students_Sync::start()` and `WPCPM_Mentors_Sync::start()` return `WP_Error` `wpcpm_sync_site_mode` before they
  read; `::run_tick()` returns at once; `::cron_auto()` and `::cron_daily()` return before they read (their schedules
  stay, so going back needs no rescheduling).
- The Sync screens (`WPCPM_Sync_Module::handle_sync()` and `::handle_tick()`, `modules/class-wpcpm-sync-module.php:171`
  and 101): the button "Sync now" is not drawn, and the tab says "The site keeps the students' and mentors' record since
  %s, so this sync reads nothing." A hand-made post lands on the same sentence.
- `wp wpcredits sync_mentors` (`WPCPM_CLI::sync_mentors()`, `class-wpcpm-cli.php:45`) prints "The site keeps the
  students' and mentors' record since %s. Nothing was read." and exits with an error.
- `wp wpcredits people_fill` refuses in site mode with the same words.
- The Student Duplicate Finder's scan (`WPCPM_Duplicates_Scan::CRON_SCAN`) is unscheduled by step 6 and its screen says
  it is retired (task B18 removes it).

### 8.8 What stays connected to Airtable after the switch

The Institutions table. The institutions design keeps a copy written by the site "until the students move" (its section
9), and two paths still make an institution's key by creating its Airtable row: an approved application
(`WPCPM_Institution_Approval`, `create_records()` at `modules/class-wpcpm-institution-approval.php:519`) and a promoted
lead (`WPCPM_Institution_Copy::create()`, `modules/class-wpcpm-institution-copy.php:546`). Section 17 sets out the two
options for the product owner; the plan as written assumes (a), which keeps them until January. Making the base
read-only for everyone but its owner on the day may also stop the copy, unless the site's token belongs to the owner:
that is not known today, so the dry run of 24 December tests it (8.5).

### 8.9 The way back, and its limits

Untick the setting (no guard going back). `WPCPM_People_Switch::off()` (task A30) first stamps every account that holds
a site key, or whose enrollment or mentor record changed on the site after the switch, with `wpcpm_people_kept`
(**new**); then both syncs read the frozen base at their next run and rewrite the copies. **A sync never revokes an
account so stamped** (C5): `WPCPM_Students_Sync::revoke_departed()` (2616) and `WPCPM_Mentors_Sync::phase_revoke()`
(1830) skip it and name it in their report ("kept: changed on the site after the move"), since the frozen base does
not know it. **The stamp ends** when the changes list has been typed back: `wp wpcredits people_kept --clear` (**new**,
task A30) removes every stamp and prints each account it cleared, so a kept student who later leaves in the base is
revoked by the next sync and a school's fence closes again. A registration made in site mode goes back to waiting
(`WPCPM_Student_Registration::STATE_WAITING`) on a way back, so the hourly retry writes it to the base. The record stays on the site, unread. Airtable is frozen, not deleted, for 30 days after the switch, and
the syncs' code stays for those 30 days.

What Airtable never receives: every change made on the site since the switch. `wp wpcredits people_changes
--since=<time>` (**new**) prints them from the history rows as CSV, for an Administrator to type into the base by hand.
The emails the site sent are not undone, and the automations, switched on again, may send their own for rows that
changed meanwhile: going back means reading that list first. After the first few days this is not practical. The real
protection is the weeks of shadow fills, the two rehearsals and the guard.

## 9. What changes for each person and on each screen

Every new or changed screen is built from the shared parts (`WPCPM_UI` in `class-wpcpm-ui.php`; `assets/css/parts.css`;
`docs/specs/2026-11-13-ui-shared-parts-design.md`), inside the Administrator Dashboard's frame
(`WPCPM_Dashboard_Frame`), one primary button per card, the row action the quiet button, every destructive act a
danger button, a type-and-pick field drawn by `WPCPM_Dashboards::render_combo()` (`class-wpcpm-dashboards.php:473`).
**Two parts the approved spec names do not exist yet:** `WPCPM_UI::filter()` and `WPCPM_UI::search()` (spec 2.9 and
3.3; the class has `table_open()`, `row()`, `decide()`, `item_header()` and the rest, not these two). Both are **new**,
built to the spec's own words (tasks B1, B2) before the Students view needs them. No sentence a person reads names
Airtable as a link, and none starts with "Please". Words with a count have a singular and a plural.

### 9.1 The Student Report Card

Looks as today. In site mode it reads and writes the enrollment: the answers, the hours, the screenshots, the
certificate code (read-only), the feedback forms. A student with earlier enrollments sees, under the item header, an
inner fold "Earlier tracks" listing each (track, state tag, dates) as a list row whose quiet action "Open" draws that
enrollment read-only. The current enrollment is drawn first. A graduate with no current enrollment sees their latest
one, as today. The manager line (Administrators only) gains the quiet link "Open the enrollment card". The route
`wpcpm/v1/report/<record>` draws the enrollment its key names, so a mentor of an earlier track reads the track they
mentored.

### 9.2 The Mentor Report Card

Looks as today. Each mentor's list is a view of the enrollments whose mentor is theirs (current and past, as
`wpcpm_mentees` is today), rebuilt on each change. Notes and calls keep hanging off the enrollment key. Nothing links to
a shared Airtable view.

### 9.3 The Institution Dashboard

- **Roster:** the same table, from the school's roster view, one row per enrollment. A link that carries a Students ID
  still opens the row (3.8). The states read in their one word (3.3).
- **A school's edits:** the student form writes the enrollment; the status change offers Dropped out (and Graduated
  while `graduation_offered()`), through `WPCPM_Enrollment_States::move()`. Dropped out asks "Reason" (Required, a
  select of the same reasons Administrators use). The confirm loses "Airtable": "Mark %s as dropped out? The site emails
  the student, your contacts and the tutor now, and the email cannot be recalled. The change is logged under your
  name." The guard that refused Paused (an Airtable view gap, `WPCPM_Institution_Students`' class comment) goes, since
  one record holds the state.
- **Import:** rows become enrollments at Enrolled on the offered track, with no mentor. The summary's words that name
  Airtable change to "enrolled on the site".

### 9.4 The Administrator Dashboard

**The menu** gains two views, after Talks: "Students" (`?wpcpm_view=students`, `VIEW_STUDENTS`) and "Mentors"
(`?wpcpm_view=mentors`, `VIEW_MENTORS`) (both **new**, beside `VIEW_TALKS`,
`modules/class-wpcpm-administrators-dashboard.php:58`).

**With the setting off (from release 2), exactly this shows** (I15): the two views, read-only, each with the muted line
"Read only until the site keeps the record on 28 December. Until then, change students and mentors in Airtable.", the
lists, the filters, the search and the cards' tables and folds (Feedback, History, Earlier enrollments). **What waits for
the switch:** every form, decision and export on both views and their cards; the Overview's queue "Students waiting for
a decision"; the Graduate action and the new intro on "Students waiting to graduate". With the setting off those cards
are exactly as today.

**The Students view.**

- Head: title "Students"; lede "%d current enrollment; %d waiting for a decision." / "%d current enrollments; %d waiting
  for a decision." (each count its own singular and plural).
- The bar (`WPCPM_UI::filter()`, `::search()`): "Show": "Current", "Waiting for a decision", "Paused", "Pending
  graduation", "Graduated", "Left", "All" ("Left" holds Dropped out, Not continuing, Did not complete and Spam); the
  search "Find a student", hint "A name, an email address or a WordPress.org username.", button "Find".
- An inner fold "Narrow the list" (closed): "Track" (a select of the published tracks and "Not recorded"),
  "Institution" (type and pick), "Mentor" (type and pick, "No mentor" first), and the secondary button "Show".
- The table (`WPCPM_UI::table_open()`): six columns, so not `wide` (the spec's rule: `wide` for seven or more): Student
  (row header, the name linked to the enrollment card, the institution as its second line), Track, State (the tag),
  Mentor ("No mentor", muted), Started, Hours (`WPCPM_Program::hours_text()`). The name is the row's one link.
- Empty: "No student matches."
- At the list's foot, a quiet button "Download as CSV", its consequence "The students this list shows, with their
  track, state, institution, mentor and dates. No answers, notes or accessibility needs." (D1).

**The enrollment card** (`?wpcpm_view=students&wpcpm_enrollment=<key>`, `ARG_ENROLLMENT` **new**):

- Item header (`WPCPM_UI::item_header()`): the name linked to the Student Report Card, the key as the reference; line 2
  the state tag, then the track; the institution; "Started %s"; "Mentor: %s" or "No mentor".
- A key and value table: Track, Institution, Mentor, Tutor, Start date, End date, Hours, Welcome email ("Sent on %s",
  "Before the move", "Not sent yet"), Certificate code ("Not given yet"), Graduation date ("Not recorded"; or "Graduates
  on %s"), Reason (Dropped out only).
- An inner fold "Change the details" (closed): "Track" (select; hint "Answers the student saved for questions the new
  track does not ask stay on the record and are not shown."), "Institution", "Mentor" (type and pick, "No mentor" first;
  hint "A new mentor is emailed about this student."), "Tutor" (type and pick from `WPCPM_Institution_Tutors::list_for()`),
  "Start date", "End date". The primary "Save the details". Outcomes: "The details are saved." and "The details are
  saved, and the mentor has been emailed."
- The Decide area (`WPCPM_UI::decide()`), only the moves allowed from the state (3.3):
  - "Enroll" (primary, a panel): "Track" (Required), "Mentor" (Optional), "Start date" (Required), a checkbox "Send the
    welcome email now, with no mentor"; confirm "Enroll the student"; hint "With a mentor, the student gets the track's
    welcome email now. With none, it goes when a mentor is set, unless you tick the box."
  - "Pause" (secondary, at once): "Pause this enrollment? Nobody is emailed."
  - "Resume" (primary from Paused, at once): "Resume this enrollment? Nobody is emailed."
  - "Mark as pending graduation" (secondary, at once): "Mark this student as pending graduation? Nobody is emailed."
  - "Graduate" (primary, a panel): "Graduation date" (Required; hint "Today or a later day."); confirm "Graduate the
    student"; hint "On that day the student gets the certificate email, with their mentor in copy."
  - "Back to the track" (secondary from Pending graduation, at once): "Send this student back to their track? Nobody is
    emailed."
  - "Drop out" (danger, a panel): "Reason" (Required), "Note" (Optional); confirm "Record that the student dropped out";
    hint "The student, their institution's contacts and their tutor are emailed. The note stays here and is sent to
    nobody."
  - "Not continuing" (danger, a panel): "Note" (Optional); confirm "Record that the student is not continuing"; hint
    "The student, their institution's contacts and their tutor are emailed. The note is sent to nobody."
  - "Did not complete" (danger, at once): "Record that the student did not complete? Nobody is emailed."
  - "Mark as spam" (danger, at once, from Waiting for a decision): "Mark this registration as spam? Nobody is emailed."
  - "Reopen" (secondary from a closed state, a panel): "Why" (Required); confirm "Reopen the enrollment"; hint "Nobody is
    emailed. The student's access comes back at once. The reason is kept in the history."
  - "Send the welcome email" (secondary, at once, while Enrolled and not sent): "Send the track's welcome email to this
    student now?"
- An inner fold "Notes": each note (date, author, `WPCPM_UI::note()`), newest first; "Add a note" (a text area) and the
  secondary "Add the note"; hint "Seen by Administrators only."
- Inner folds "Feedback" (each form's answers, read-only; "Not sent yet"), "History" (when, who, one plain sentence:
  "Mentor changed from %1$s to %2$s.", "Moved from Enrolled to Dropped out: %s."), and "Earlier enrollments" (list
  rows, linked).

**The Mentors view** (`?wpcpm_view=mentors`, in `WPCPM_Mentors_View`, **new**):

- Head: title "Mentors"; lede "%d active mentor; %d with no current student." / "%d active mentors; %d with no current
  student."
- "Show": "Active", "Inactive", "All"; search "Find a mentor", hint "A name, an email address or a WordPress.org
  username."
- Table (five columns): Mentor (row header, linked to the mentor card), Country, Current students, Since, State ("Active"
  green, "Inactive" neutral).
- The mentor card (`&wpcpm_mentor=<key>`): item header (name linked to the WordPress.org profile, the key; line 2 the
  tag, then country, languages, "Since %s"); a key and value table; an inner fold "Change the details" with "Country",
  "Languages", "Expertise" (type and pick, several), "Available hours per week", "Sponsored" (a checkbox), "Sponsor"
  (type and pick from the site's sponsors), "List me as looking for a sponsor" (a checkbox), and the primary "Save the
  details"; the Decide area: "Make inactive" (danger, a panel: "Note", Optional; confirm "Make the mentor inactive";
  hint "Their current students keep them as their mentor until you change it on each enrollment. The mentor keeps their
  account. Nobody is emailed.") or "Make active" (primary, at once: "Make this mentor active again? Nobody is
  emailed."); inner folds "Students", "Notes", "History".

**The Overview's Waiting for you** (site mode): a queue "Students waiting for a decision", oldest first, in the shape of
the other queues (`WPCPM_Administrators_Cards`), its phrase "%d registration" / "%d registrations", its empty line "No
registration waits for a decision.", its foot the way to the Students view filtered by the state.

**"Students waiting to graduate"** (`WPCPM_Administrators_Cards::render_graduation()`,
`modules/class-wpcpm-administrators-cards.php:3635`) in site mode gains the row action: a quiet "Graduate" opening a row
form with "Graduation date" (Required; hint "Today or a later day. On that day the student gets the certificate email,
with their mentor in copy.") and "Graduate the student", "Cancel". Its intro (`:3701`) becomes "These students have
finished and wait for an Administrator: read their Student Report Card, ask the mentor if something is unclear, and
approve the quiz on Learn. Then press Graduate and choose the day." Its foot goes to the Students view filtered by
Pending graduation.

**The Watch view** gains the card "Students and mentors move" (release 1, shown until 30 days after the switch): its
phrase made of two counted parts, each with its singular and plural: "%d row blocks the move" / "%d rows block the
move", then "%d is good to know." / "%d are good to know.", the blocking
rows as a table (Case, Who, Airtable rows, What to do), a closed inner fold "Good to know", the counts (persons,
enrollments by state, mentors by state, files listed and stored), and during the freeze the day's steps with their
stamps. With nothing blocking: "Nothing blocks the move. The last fill finished %s."

### 9.5 Registration

The public form is unchanged. In site mode `WPCPM_Student_Registration_Process::settle()` makes an enrollment at Waiting
for a decision with the registration's answers, on the account if one exists, else held by address; the registrant's
acknowledgement (`student-registration-received`) is unchanged; the Administrator of the area is told (10.1).

### 9.6 The accounts

In site mode **`WPCPM_People_Accounts`** (**new**, task A43) makes and revokes student accounts on each move, as the
sync does today: a person whose enrollment becomes Enrolled and who has no account gets one by `provision_student()`'s
rules and fence (`adoption_block()`), and the site's invitation is queued (`WPCPM_Mail::queue_invite()`) while
`send_welcome_email` is on; an enrollment that closes takes the role under `student_on_inactive`, except Graduated,
which keeps it. No run revokes in bulk.

### 9.7 Mentor application, activation and the Mentor Status Checker

The application form and queue are unchanged. Activation in site mode writes the mentor record (Active, `mnt...`, its
application) and the account as today (`WPCPM_Mentor_Provision`), and no Airtable row: "Write the Airtable row again"
is not drawn. The Mentor Status Checker (`WPCPM_Mentor_Checker_Runner`) reads only the site's selected applications
(`site_rows()`), the 67 imported Vetted - positive rows among them, and promotes through the activation. Its daily cron
(`wpcpm_checker_weekly_check`) and its daily chat message are unchanged.

## 10. Emails

### 10.1 What the site sends after the switch

All in a **new** area, Enrollments, of the catalog (`mail/class-wpcpm-mail-catalog.php`) and its templates file
(`mail/templates/enrollments.php`, **new**). The catalog is a fixed list today; it gains one entry per published track,
made from `WPCPM_Program::labels()`, so a new track has its welcome the day it is published (task B3).

| Email (id, all new) | Sent when | To | Replaces |
|---|---|---|---|
| Track welcome (`enrollment-welcome-<track key>`) | Once per enrollment that starts after the switch: the first time it is Enrolled with a mentor; or when an Administrator ticks "Send the welcome email now, with no mentor" or presses "Send the welcome email" | the student | the eight track welcomes |
| Not continuing (`enrollment-not-moving-forward`) | The enrollment moves to Not continuing | the student; the school's contacts and the tutor in copy | the Not moving forward notice |
| Dropped out (`enrollment-dropped-out`) | Dropped out with any reason but the withdrawal one | as above | the Dropped out notice |
| Withdrawal (`enrollment-withdrawn`) | Dropped out with the withdrawal reason | as above | the Withdrawal notice |
| Certificate (`enrollment-certificate`) | The enrollment becomes Graduated on its day | the student, the mentor in copy | the certificate notice |
| New student registered (`enrollment-registered`) | A registration lands at Waiting for a decision | the Administrator of the area (`WPCPM_Institution_Areas::administrator_for()`, `modules/class-wpcpm-institution-areas.php:173`), else every Administrator (`WPCPM_Institutions::managers()`) | the notice to one Administrator |

Unchanged, sent by the site on the change instead of after a sync: "Student assigned, to the mentor"
(`student-assigned-mentor`, `WPCPM_Mentor_Assigned`, with its told set, its ceiling of 25 and its brake), hooked to
`wpcpm_enrollment_changed` when the mentor field changes (task B23); the site's invitations. Retired: the two emails for
one school (D9); the Pending graduation email to the program inbox, with its Airtable form (decision list, 20).

The Dropped out reasons, and which one sends the withdrawal notice, are read from the base's schema before the build
(14.1).

### 10.2 Never twice, never missed

- **Nothing that happened before the day sends an email** (C6). At the switch (8.4, step 3) every enrollment is stamped
  in `_wpcpm_enr_mailed` as `before`: one at Enrolled, Paused or Pending graduation as welcomed; a closed one as told;
  a graduated one as notified. Only an enrollment that starts, leaves or graduates after the switch sends. The one
notice the freeze holds back, "New student registered" for a registration made during it, is sent by the switch's
step 8, once per registration (8.4).
- **The doubtful ones are a list, not a send.** Enrollments that were Enrolled before the switch with no mentor (no
  Airtable welcome went, since the automations wait for a mentor) are a Good to know list on the Watch card: "Enrolled
  before the move with no mentor: no welcome was sent. Send it from the card if it is still owed."
- **Stamped under the lock.** The sender reads `_wpcpm_enr_mailed` under the person's lock, sends only when the id is
  absent, and writes the stamp once the mailer accepted it; a refused email is not stamped and is tried again at the
  next move or by the Administrator's button.
- **The fill, the import and the switch send nothing** (`WPCPM_Mail_Capture` asserts it in their suites).
- **The automations go off before the switch**, inside the freeze, so no row re-enters a status between the two.
- Every email is logged in the mail log (`WPCPM_Mail_Log`).

### 10.3 The private texts (D8)

The shipped texts hold no private link: each welcome names the course through `{course_link}` (the track's Learn
course, `WPCPM_Program::course_url()`), the mentor through `{mentor_name}` and `{mentor_email}` (optional: for a student
with no mentor `WPCPM_Mail_Templates::fill()` leaves out the sentence of an optional placeholder that is empty), and a
line `{private_links}` empty in the shipped text. The person who owns the texts reads each in Tools > Emails, pastes the
private parts, saves, and sends a test to the one test address; the saved text lives in the site's database
(`WPCPM_Mail_Templates::OPTION`, `wpcpm_mail_templates`), never in the repository. Guard condition 10 wants every one
saved.

## 11. Certificates and graduation (D10)

### 11.1 Graduating

An Administrator presses Graduate on the enrollment card or the "Students waiting to graduate" card and chooses today
or a later day (decision list, 16). Today: the move is made at once. A later day: `_wpcpm_enr_graduates_on` is set and
the state stays; the **new** daily cron `wpcpm_enrollments_daily` (00:15 UTC) moves each enrollment whose day has come,
writes the graduation date and sends the certificate email. The Sensei quiz approval on Learn stays a person's step. A
school's Graduate press (while offered) is a graduation dated today.

### 11.2 The certificate code

A track's definition (`tracks/class-wpcpm-track-definition.php`) gains an optional `certificate` (**new**): `questions`
(the columns that must be answered; by default those its questions mark `required`) and `hours` (a number, or none).
`WPCPM_Track_Definition::validate()` refuses `hours` on a track with no hours target (`WPCPM_Program::has_hours_target()`,
`class-wpcpm-program.php:252`) with "A track with no hours target has no hours condition for its certificate."

**What is stored where.** The code is one per track. It is typed with `wp wpcredits certificate_code <track key>`
(**new**, task B25, the code read from standard input, never from an argument, so it stays out of the shell's history),
and from release 3 also on the track's page in the Track Builder ("Certificate code", hint "Kept sealed on the site and
shown to the student on their Student Report Card once the track's rule is met."). It is kept sealed in
`wpcpm_certificate_codes`. **An enrollment holds no copy of the code:** `_wpcpm_enr_graduation` holds the day it was given
and the track, and the Student Report Card unseals the track's code when it draws the card for that student. An
imported row whose code equals its track's code is held the same way; one whose code differs (an older code) keeps that
code sealed in its own `_wpcpm_enr_graduation` entry (`WPCPM_Secret::seal_for_option()`) and is listed as Good to know.
So a reader of the database finds no code in the clear anywhere.

When a Student Report Card save leaves the enrollment meeting its track's rule, the site records the day, once; it is
never taken away by the site. The three tracks that have a code automation today (Foundations Track, Intro to
Contribution, the one school's variant) get a `certificate` seeded from those automations' rules (their questions and
hours, 100 or 50, read read-only before the build, 14.1); every other track has none until an Administrator adds one.

## 12. The outside readers (D5, D6, D12)

Nothing is copied back to Airtable for students or mentors (D5). The feed and the readers are built by stream B (14.2),
in classes of their own and in their own repositories.

### 12.1 One public feed of aggregates

`GET /wp-json/wpcpm/v1/program-figures` (`WPCPM_Program_Figures_Feed`, **new**), the shape of
`WPCPM_Mentor_Application_Feed`: public, metered per address through `WPCPM_Ceiling`, built once every 15 minutes, from
the reader (so before the switch it answers from the copies and its figures do not jump on the day). It holds `v`,
`built`, `months` (per calendar month: registered, enrolled, graduated, dropped out, not continuing, mentors
activated), `now` (current enrollments by track, by country, by institution for confirmed schools; mentors active and
inactive; students with and with no mentor), and `feedback` (the counts of each answer of the rating and choice
questions the public dashboard reads). **Never:** a name, an address, a username, a key, a free text answer, a note, a
reason, an accessibility answer. A count under 5 broken down by country, institution or answer is given as `"<5"`
(decision list, 23).

### 12.2 Student Impact, on the same site

Student Impact (active on the live site) reads graduates' name, WordPress.org username and main team today
(`SI_Sync::phase_fetch()` in its own repository). A **new** in-process filter, `wpcpm_graduates`, the way Credits Program
Mentors reads `wpcpm_public_mentors` (`WPCPM_Mentor_Directory::FILTER`), answers the graduated enrollments with a
username: name, username, team, and the count of all graduates. No address. Student Impact's own release reads it.

### 12.3 The rest

- **WPCredits Tracker** (active on the live site): reads the public feed in a release of its own, or retires (D6). Its
  grade figures end at the switch either way (decision list, 27).
- **The public dashboard and the monthly card** (`build_dashboard.py`, `build_monthly.py` in the WordPress/WPCredits
  repository): read the feed, and their sponsors read moves to `wpcpm/v1/sponsors`.
- **Credits Monthly Report** (not active on the live site): reads the feed.
- **Credits Program Mentors:** its `source` setting set to site.
- **Mentors and schools through shared views:** the Mentor Report Card and the Institution Dashboard (D15).

### 12.4 The post-graduation snapshots (D12)

`scripts/post_grad_snapshot.py` writes a row per graduate per month to Airtable today. Under D12: two **new**
authenticated routes, `GET /wpcpm/v1/graduates` (username, graduation month, track; no address) and `POST
/wpcpm/v1/snapshots` (kept as private posts of the **new** type `wpcpm_grad_snapshot`), allowed only to an account holding
the **new** capability `wpcpm_snapshots`, used by the script through an application password kept as a secret of the
repository's workflow, never in its files. The existing Airtable rows go into the archive (7.4).

## 13. Privacy (D17)

### 13.1 What the site newly holds

Report answers and screenshots of every enrollment, not only of signed-in students; every feedback answer, free text
included; the registration answers and accessibility needs; Dropped out reasons; graduation records; the post-program
addresses and the mentoring opt-in; the Administrators' notes and record comments about students and mentors; mentors'
application answers and directory fields. The three most personal of these (accessibility needs, feedback free text,
notes and comments) arrive only with release 2, beside the tools (6.1).

### 13.2 Exporter and eraser

`WPCPM_People_Privacy` (**new**, tasks B7, B8), registered on `wp_privacy_personal_data_exporters` and
`wp_privacy_personal_data_erasers` as `WPCPM_Institution_Leads` and `WPCPM_Student_Interest` are: by address, every
enrollment, its feedback, its notes and its files, and the mentor record. The eraser removes the answers, the files,
the notes, the reason, the accessibility answer and the addresses, and keeps the state, the track, the institution and
the dates under the name "Erased", so the counts and the feed hold.

### 13.3 Retention, and the views

Proposed, his under D17: accessibility needs deleted 90 days after the enrollment closes; a registration marked Spam
deleted after 30 days; everything else kept while the program runs, with the yearly review the Privacy Notice already
promises. Nothing is deleted by a schedule before he confirms the numbers. The views stay narrow: the roster index
keeps refusing accessibility needs and free text (`WPCPM_Roster_Index::KEYS`, `clean()`), the list view holds no
answer, the CSV export no answer or note, the public feed nothing personal. The Privacy Notice is updated before the
switch (guard condition 12).

## 14. The plan

### 14.1 Before the tasks (the controller's, read-only, by Tuesday 24 November)

The fixtures `bin/fixtures/students-table-fields.json`, `reports-table-fields.json`, `feedback-table-fields.json` and
`mentors-table-fields.json` were read in early September (29, 74, 64 and 32 fields); the base has 33, 104, 64 and 32
now. They are read again from the base's metadata (names, types, choices, no records), with the Dropped out reason's
choices, a **new** `lessons-table-fields.json`, and the rules of the three certificate code automations and the three
leaving notices. `bin/test-fixtures.php` then pins every column the record names.

### 14.2 Two streams

The record, the fill, the switch and the screens that share the dashboard's files are **stream A** (one committer, one
worktree). The feed, the outside readers, the two shared parts, the privacy tools and the readers that live in classes
of their own are **stream B** (its own committer and worktrees, one per repository). A task is about half a day of
building with its checks, buildable and reviewable alone, and keeps the setting off unchanged.

**One owner per shared file.** Never two committers in one file at a time. The owner commits; the other stream hands
its change over as a written list (the lines to add, in the task's report) at the named point, and the owner applies it
the same day.

| Shared file | Owner | How the other stream's change reaches it |
|---|---|---|
| `wpcredits-program-manager.php` (the requires) and `uninstall.php` | A | B's new classes listed in each B task's report; A adds them at its next commit, and at the latest at the merge points below |
| `class-wpcpm-privacy-guard.php` | A | B7 hands over `wpcpm_grad_snapshot` (B15) the same way |
| `class-wpcpm-cli.php` | A | B25's `certificate_code` and B15's routes need no command beyond it; B25 hands over the command's method as a list, applied by A |
| `mail/class-wpcpm-mail-catalog.php`, `mail/templates/enrollments.php` | B (B3, B4) | A45 and A46 name the email ids and placeholders they send in their task reports; B adds them |
| `modules/class-wpcpm-student-report-form.php` | A (A34, A35) | B25 provides `WPCPM_Certificate_Codes::for_enrollment()` (**new**) in its own class; A35 calls it |
| `readme.txt`, the version, the pot | A (A24, A51, A52, A53) | B's changelog lines in its reports, written in by A's release task |
| `bin/test-roles.php`, `bin/test-privacy-guard.php`, `bin/test-cli.php` | A | B's expected lines in its reports, applied by A with the files above |

**Merge points** of B's plugin branch into A's release 2 branch: Monday 7 December (B1, B2), Monday 14 December (B3 to
B8), Wednesday 16 December (the rest). Each merge is done by A, after B's suites pass.

**What each needs from the other, and when:**

- B needs from A: the enrollment store and the states' words (A3 to A6) by Friday 27 November; the reader's shape (A9,
  A10) by Monday 30 November; the mentor record (A7) by Tuesday 1 December.
- A needs from B: `WPCPM_UI::filter()` and `::search()` (B1, B2) by Monday 7 December (A48); the per-track email
  catalog (B3) by Tuesday 8 December (A45); the exporter and eraser (B7, B8) by Monday 14 December (guard condition 12,
  and the most personal fields joining the fill); every B plugin task merged into release 2 by Wednesday 16 December.

**Stream A, release 1, the record underneath (24 tasks):**

- A1 `WPCPM_People_Keys`: the shapes and minting.
- A2 `bin/test-people-keys.php`: the source check of 3.9, its list pinned.
- A3 `wpcpm_enrollment`: post type, insert, get, find by key and by old IDs; privacy guard list; uninstaller.
- A4 Enrollments by person, current, the one-current rule, the person's lock; `patch()` and the two actions
  `wpcpm_enrollment_moved` and `wpcpm_enrollment_changed`.
- A5 `WPCPM_Enrollment_States`: the map, the one words, tones, the allowed moves (pure).
- A6 `move()`: lock, closed-from, history row, the audit's new keys, ground and kinds, the action.
- A7 `wpcpm_mentor_rec` and `WPCPM_Mentor_Records`.
- A8 `wpcpm_person_note` and `WPCPM_Person_Notes`.
- A9 `WPCPM_People`, the off branch: the doors on the old getters, every answer unchanged.
- A10 `WPCPM_People`, the on branch, from the record.
- A11 `WPCPM_People_Views`: the roster options and the list view.
- A12 Views: mentee lists, mentor cards, directory and sponsorship options (`WPCPM_Mentor_Directory::OPTION`,
  `WPCPM_Mentors_Sync::OPT_SPONSORSHIP`), and the account keys of 5 (I8).
- A13 `WPCPM_People_Fill::plan()`: finding the account (3.1) and the pairing rule (3.2).
- A14 `plan()`: the status map with the extra statuses (I7), feedback placement, mentor rows, the cases of 6.3.
- A15 `apply()`: writes by hash, staging between ticks, the count guard, rows gone listed, `--remove` and the
  `wpcpm_removed` status, the held fields left out.
- A16 The students sync's `record` phase and `people_shadow`.
- A17 The mentors sync's `record` phase; `wp wpcredits people_fill`.
- A18 Screenshots fetched in the tick, stored, counted; the account's own copied and left in place (I12).
- A19 `WPCPM_People_Parity` and `wp wpcredits people_parity`.
- A20 The Watch card "Students and mentors move".
- A21 `people_import`: read and plan (applications, archived mentors), `--save`, `--from`, `--dry-run`.
- A22 `people_import`: apply, idempotent, `--since-last`, `--comments-only`.
- A23 `--archive`.
- A24 Guides, readme, version.

**Stream A, release 2, the site reads its own record (27 tasks):**

- A25 `people_on_site`, the settings row (state shown, off path), `WPCPM_People_Switch::off()`.
- A26 The guard of 8.3, each condition and sentence (pure, on stand-ins); `people_owed`.
- A27 `people_freeze`: the state, the sentence, the command, the banner lines, the history row.
- A28 The freeze in every writer of 8.2 (the four added in round 4 among them), each keeping what was typed; the
  syncs' cron and ticks stopped while frozen.
- A29 The syncs gated at start, tick, cron, the Sync screens and `sync_mentors`; the Track Builder's Airtable step skipped
  in site mode; the scan.
- A30 The way back's safety (C5): `wpcpm_people_kept`, the two revokes skip it, `people_kept --clear`; registrations
  back to waiting; `people_changes`.
- A31 `people_switch`: the steps of 8.4, stamps, resume, step 1's comparing fill, step 8's notices, `--abandon`
  (refused after step 7, removing step 2's enrollments), the drawn-value check on the Advanced tab.
- A32 The switch's steps 2 and 3: frozen registrations and form 3 sends; the emails' stamps (C6).
- A33 Step 4's whole rebuild, timed on 1,250 enrollments.
- A34 The Student Report Card: answers and hours on the enrollment.
- A35 The Student Report Card: files per enrollment, the route by key, "Earlier tracks".
- A36 The feedback forms on the enrollment.
- A37 Mentor notes, calls, group sessions, questions and the AI Mentor transcripts on the keys.
- A38 The Institution Dashboard: roster, claim, student view, export.
- A39 A school's edits and status change (with the reason).
- A40 The school import makes enrollments.
- A41 Registration makes an enrollment waiting for a decision.
- A42 Activation makes a mentor record; the checker on site rows.
- A43 `WPCPM_People_Accounts`.
- A44 Graduation on the record: `form_finished()`, the Graduate move with a day, `wpcpm_enrollments_daily`.
- A45 Emails: the stamps, the welcome's rules, the leaving and certificate triggers (with B3's catalog).
- A46 Emails: recipients (school contacts, tutor, area Administrator), the registration notice.
- A47 The enrollment card: header, table, "Change the details".
- A48 The Students view: list, filter, search, CSV.
- A49 The enrollment card: the Decide moves and their panels.
- A50 The enrollment card: notes, feedback, history, earlier enrollments; the Overview's queue and the graduation card's
  action (site mode).
- A51 Guides, readme, version.

**Stream A, release 3 (2 tasks):** A52 and A53, held for what the first rehearsal finds.

**Stream B (25 tasks):**

- B1 `WPCPM_UI::filter()`, to the spec. B2 `WPCPM_UI::search()`, to the spec.
- B3 The mail catalog's Enrollments area and one welcome per published track. B4 The templates' shipped texts and their
  placeholders.
- B5 `wpcpm/v1/program-figures`: shape and counts from the reader. B6 Its small-count rule, cache, ceiling, suite.
- B7 `WPCPM_People_Privacy`: exporter. B8 Eraser, and the retention rules (not scheduled until D17).
- B9 `WPCPM_Mentor_Engagement`, `WPCPM_Ability_Answers`, `WPCPM_Student_Flags`, the wp-admin Students dashboard and table
  on the keys and the reader.
- B10 `WPCPM_Outcome_Figures` and `WPCPM_Semester_Report` on the record.
- B11 `WPCPM_Mentors_View`: list and filter. B12 The mentor card: details form, decisions, students, notes.
- B13 `wpcpm_graduates`. B14 Student Impact's release reading it (its repository).
- B15 The snapshot routes, capability and post type. B16 `post_grad_snapshot.py` on them (the WordPress/WPCredits
  repository).
- B17 `build_dashboard.py` and `build_monthly.py` on the feed.
- B18 The Student Duplicate Finder retired: its screen, card and cron removed, its vault readable for 30 days (release 3).
- B19 The WPCredits Tracker on the feed, or its retirement (its repository).
- B20 The Credits Monthly Report on the feed (its repository).
- B21 The Track Builder: the certificate fields on the track's page (release 3).
- B22 Credits Program Mentors' `source` set to site, checked on the Docker copy; B's guide sections.
- B23 `WPCPM_Mentor_Assigned` hooked to a mentor change on the site.
- B24 The certificate rule in the track definition and its validation.
- B25 The sealed codes, `certificate_code`, the day recorded when met, the card unsealing.
  (B23 to B25 sit in classes of their own: `WPCPM_Mentor_Assigned`, `WPCPM_Track_Definition`, the codes store and
  command; merged into release 2.)

**Totals:** release 1, 24 (A); release 2, 27 (A) and 17 (B1 to B13, B15, B23 to B25, merged into the plugin); release 3,
2 (A) and 2 (B18, B21); the outside repositories, 6 (B14, B16, B17, B19, B20, B22). **Stream A 53, stream B 25: 78
tasks.**

### 14.3 What each release changes with the setting off

- **Release 1:** Settings > Advanced shows the new box of `people_shadow`, "Fill the site's record from Airtable at
  each sync", off; Students > Sync and Mentors > Sync show one more phase line, "Filling the site's record", in their
  progress once it is on; the Watch view shows "Students and mentors move" to Administrators. Nothing else.
- **Release 2:** the Students and Mentors views, read-only, with their one line (9.4); the "Students' and mentors'
  record" row on Settings > Advanced, off; nothing else. The freeze, the forms, the decisions, the queue and the
  graduation action are present in the code and shown only when their state is on.
- **Release 3:** the Student Duplicate Finder is gone (its scan already stops at the switch, and the owner's standing
  word retires it); the Track Builder's certificate fields.

### 14.4 Rehearsals, with real data, on the Docker copy

The Docker copy (localhost:8900, mail swallowed, Airtable refused by its safety plugin) is shared: before each
rehearsal, check no other session's trial is on it.

- **Release 1, Monday 7 December**, the working day after release 1 is out (the command exists on live only then):
  on the product owner's word, `wp wpcredits people_import --dry-run --save=-` on live (a read through the site, nothing
  written; `people_shadow` still off, so there is no fill, and a saved read asks for none: section 7), piped over ssh into the program's private folder; on Docker, a stand-in
  client serving that read (the institutions rehearsal's, which records every request); `people_fill` and the import
  from it. Checked, counts only: persons, enrollments by state, mentors by state, applications made, rows archived, each
  case of 6.3, and the pairing counts of 6.3; the four names, a report answer and a file name read back with `HEX()`;
  files planned (their links have expired, so the rehearsal counts and does not fetch); a second run adds nothing. Only
  when it passes does `people_shadow` go on, on the product owner's word.
- **First rehearsal, Thursday 17 December** (release 2's build): a read saved that day; the freeze on; each guard
  condition made to refuse once; `people_switch` stopped at each step and resumed; `--abandon` once; then the switch; a
  day of writes (enroll with and with no mentor, assign a mentor, pause, resume, graduate today and on a later day with
  the daily cron run by hand, drop out with each reason, a registration made during the freeze, a school's import, a
  Report Card save that meets a certificate rule, Feedback form 3); every email captured once and none on a repeated
  move; the readers' feeds read; the way back (the kept accounts not revoked); `people_changes` printed.
- **Second rehearsal, Tuesday 22 December** (release 3): from a read saved that day, the day's list of 8.5 run in order
  against the stand-in, each step timed against its line, read with the product owner. Its fixes go into one small
  release on **Wednesday 23 December**, the last before the day (A52, A53 if the first rehearsal left them free, else
  one held half day). A fix larger than that: if its item is on the slip list, the item slips; if it is a never-cut
  item, the product owner decides on 23 December whether the sitting goes on 28 December with that fault named, or not
  at all in December.

### 14.5 The calendar

| When | Stream A | Stream B |
|---|---|---|
| Tuesday 24 November | The twelve decisions answered, or the working assumptions stand; the fixtures read | |
| Monday 23 November to Thursday 3 December, noon | Release 1 built (24 tasks; Monday 30 November, the institutions switch, counted as half a day) | B1, B2 (parts); then B3, B4 |
| Thursday 3 December, afternoon | Release 1's checks | B5, B6 |
| Friday 4 December | Release 1 released, `people_shadow` off | B7, B8 |
| Monday 7 December | Release 1 rehearsal, from a read taken with the released command | |
| Tuesday 8 December | `people_shadow` on, on the product owner's word once the rehearsal passed; the first live fill | |
| Weekly from 8 December | `people_import --since-last` on live; the report read; rows fixed in the base | |
| Friday 4 December to Wednesday 16 December | Release 2 built (27 tasks) | B9 to B13, B15, B23 to B25; then B14, B16, B17, B19, B20 in their repositories |
| Thursday 17 December | Release 2 released, setting off; first rehearsal | the readers' releases, each reading the feed served from the copies |
| Friday 18 December, Monday 21 December | A52, A53; release 3 on Monday 21 December | B18, B21, B22 into release 3 |
| Monday 21 December | The email texts saved (D8), the codes and the Privacy Notice; the message of 14.8 sent | |
| Tuesday 22 December | Second rehearsal | |
| Wednesday 23 December | The last small release: the second rehearsal's fixes; no plugin release after it | |
| Thursday 24 December | The forms closed, the shares off, the slipped readers off; the dry run of the whole list (8.5) | |
| Monday 28 December, from 00:00 UTC | The day, one sitting (8.5): the owed check at 00:00, the freeze from 00:05, the switch at about 00:52, the checks and the comments pass to 01:57 | |
| Tuesday 29 December | The day-after checks (16.3) | |

### 14.6 Whether it fits

Stream A: 53 tasks at about three a working day (the institutions' pace) is about 17.7 days; from Monday 23 November to
Monday 21 December there are 21 working days, less the institutions switch's half day, two rehearsal days and the
release 1 checks: about 18. **Stream A fits with no slack, and neither of its releases has any.** Stream B: 25 tasks in the same weeks, about 8.3 days of
work in 18: about nine days of slack. **The whole fits only with the second stream.** If stream A falls behind, stream B
takes A's tasks that sit in classes of their own (A21 to A23 the import and archive, A30
`people_changes`), then the slip list starts. The date does not move.

### 14.7 What slips first

A reader that slips is **switched off at the switch** (its own sync or workflow off, by the day's step 2) and shows its
last figures; none reads the frozen base.

| # | Item (tasks) | If it waits until January | What stays connected meanwhile |
|---|---|---|---|
| 1 | The Student Duplicate Finder's removal (B18) | A dead screen; its scan stops at the switch. Costs nobody | Nothing |
| 2 | The Credits Monthly Report (B20) | Nothing on live: it is not active there | Nothing |
| 3 | The Track Builder's certificate fields (B21) | Codes are typed with `certificate_code` (B25); no track published (D16) | Nothing |
| 4 | The WPCredits Tracker (B19) | Its sync is switched off at the switch; it shows its last figures | Nothing |
| 5 | The post-graduation snapshots (B15, B16) | The workflow is paused at the switch; the next snapshot when it lands | Nothing |
| 6 | The public dashboard and monthly card (B17) | The workflows are paused at the switch; the public dashboard shows its last figures | Nothing |
| 7 | Student Impact (B13, B14) | Its sync is switched off at the switch; the showcase shows its last list | Nothing |
| 8 | The feed (B5, B6), once 4 to 7 have slipped | No outside reader reads the site | Nothing |
| 9 | The mentor card's "Change the details" (part of B12) | Mentors' directory fields cannot be changed until it lands | Nothing |
| 10 | The CSV export (part of A48) | No export | Nothing |

Never cut, since each would be an anti-goal: the record and its keys, the fill and the report, the freeze, the guard,
the switch's steps, the way back's safety, the emails and their stamps, the Students view and the enrollment card with
its moves and the mentor field, the mentor state, graduation, the accounts following the enrollment, the exporter and
eraser, the two shared parts, and the rehearsals.

### 14.8 The message of Monday 21 December

Drafted by the controller, sent by the product owner. Mentors: their students are on the Mentor Report Card; any shared
Airtable view stops on Thursday 24 December; saving on the site is paused on Monday 28 December from 00:00 to about
02:00 UTC (the message gives the hours in UTC and says so). Schools with a shared view: their students are on the
Institution Dashboard; adding students is paused for the same two hours. Administrators: the Students and Mentors
views, the freeze, and that the grid is read-only from 00:00 UTC on 28 December. Students are not emailed (decision list,
25): the Student Report Card's line during the freeze tells them.

## 15. Risks, and what is out of scope

### 15.1 Risks, worst first

1. **A student or mentor wrongly merged, lost or locked out by the fill.** Lowered by: accounts found by stamp then
   address, never by username; `adoption_block()` in the fill; no two accounts joined; the pairing rule with "cannot be
   paired" blocking; three weeks of shadow fills; the reader's two answers compared for every account; nothing deleted
   in Airtable; the guard.
2. **A change lost between the last read and the switch.** Lowered by: the freeze in every writer; the base read-only
   for all but its owner; two fills that must agree; the day-after check of rows changed in the base (16.3).
3. **An email sent twice or not at all.** Lowered by: nothing before the day sends (C6); the stamps under the lock; the
   doubtful ones listed; the automations off inside the freeze; the guard's condition 10; every email captured in both
   rehearsals, twice.
4. **The scope larger than the time.** Lowered by: two streams; the slip list decided now; decisions by 24 November;
   the version read again before each release.
5. **A half-switched site.** Lowered by: the switch's steps, stamped and resumable, the setting written last; `--abandon`;
   the hold point at 00:49 UTC on 28 December.
6. **People locked out by a way back.** Lowered by: `wpcpm_people_kept`, never revoked by a sync (8.9).
7. **A record changed in two places.** Lowered by: the base read-only; nothing copied back (D5); the views read-only
   before the switch.
8. **Administrators, mentors and schools lose their way of working in the last week of the year.** Lowered by: the views
   two weeks before, read-only; the guides in release 2; the message of 21 December; the frozen base readable 30 days.
9. **Screenshots lost to expired links.** Lowered by: fetching in the tick that read the link, from 8 December; counting;
   the guard's condition 7.
10. **A site key refused by code that expects `rec...`.** Lowered by: one key class and the suite that reads every
    caller (3.9).
11. **A sync runs after the switch.** Lowered by: every entry gated (8.7).
12. **Double-encoded names.** Lowered by: WordPress's own tables, the institutions' proof repeated.
13. **More personal data on the site.** Lowered by: the most personal fields only beside the tools; D3's backups.
14. **The outside readers break quietly.** Lowered by: the feed answering from the copies before the switch; a slipped
    reader switched off, never reading a frozen base.

### 15.2 Out of scope

Ending the institutions copy and making institution keys on the site (section 17 sets out the choice); deleting the
base or any table; the Campus Connect base and the Education Programs Map; mentors editing their own directory fields;
bulk changes; a tool that merges two accounts; importing Lessons onto the site; a public page of graduates beyond
Student Impact's.

## 16. Testing

### 16.1 Which suites hold which contract

New suites, each `php bin/test-<name>.php` from the plugin root: `test-people-keys.php` (shapes, minting, every caller
of 3.9); `test-enrollments.php` (store, old IDs, one current, the lock); `test-enrollment-states.php` (every allowed and
refused move, the one words, closed-from, history, action); `test-mentor-records.php`; `test-person-notes.php`;
`test-people-reader.php` (each method's two branches; the old getters unchanged with the setting off);
`test-people-views.php` (the account keys of I8 for every move); `test-people-fill.php` (finding the account by stamp
then address and never by username; `adoption_block()`; the pairing rule and "cannot be paired"; the extra statuses;
every case of 6.3; an incomplete fill marks nothing gone; a gone row stays until `--remove`; held fields absent before release 2; no email, no Airtable write,
no account); `test-people-parity.php`; `test-people-move.php` (run twice is once; `--since-last`; `--save` refused in a
repository); `test-people-freeze.php` (every writer of 8.2 refuses with the sentence and keeps what was typed; reading
goes on; the registration waits); `test-people-switch.php` (each guard condition refuses with its sentence; each step
stopped and resumed; the setting written last; `--abandon`; the box refuses turning on); `test-people-way-back.php` (kept
accounts never revoked by either sync); `test-sync-site-mode.php` (start, tick, cron, the screens and `sync_mentors`
read nothing and print their words); `test-enrollment-emails.php` (each trigger and recipient; the `before` stamps;
nothing for before the day; never twice); `test-students-view.php`, `test-enrollment-card.php`, `test-mentors-view.php`
(markup on the shared parts; read-only with the setting off; hand-made posts refused); `test-ui-filter-search.php`;
`test-people-accounts.php`; `test-people-privacy.php`; `test-program-figures-feed.php` (nothing personal; small counts
hidden).

Existing suites that pin a list and change with this work: `test-privacy-guard.php`, `test-mail-templates.php`,
`test-emails-templates.php`, `test-mail-catalog.php`, `test-settings.php`, `test-institution-history.php`,
`test-audit.php`, `test-cli.php`, `test-roles.php`, `test-fixtures.php`, `test-submit-guard.php`, `test-ui-layout.php`,
`test-ui-parts.php`, `test-uninstall.php`, `test-sync-guard.php`, `test-students-sync.php`, `test-mentors-sync.php`,
`test-report-form.php`, `test-feedback.php`, `test-mentor-checker.php`, `test-institution-roster.php`,
`test-institution-import.php`, `test-student-flags.php`, `test-students-screen.php`, `test-track-definition.php`.

**One check across everything:** a client that records every Airtable request, handed to every screen, handler, cron
and command in site mode, asserted unused (the institutions plan's Review Focus 2).

### 16.2 The rehearsals' own checks

Those of 14.4, written down before each rehearsal with the expected number beside each; the report kept under the
release folder.

### 16.3 The checks of the day

On Monday 28 December, 01:07 to 01:57 UTC, after the switch: the enrollment and mentor counts are the fill's; five students and five mentors
compared with the frozen base (state, track, mentor, institution, dates, hours, answers on the Student Report Card); one
move on the test institution's test student, its email in the mail log, sent to the one test address; the Students view,
an enrollment card and a Mentor Report Card opened; no Airtable request about a student or a mentor in the request log;
`people_parity` says nothing blocks; the freeze lifted and a Report Card save accepted; each registration made during
the freeze has its notice in the mail log once; the comments pass (step 7b) finished and its count read. On 29 December: the first
`wpcpm_enrollments_daily` in the cron log; the Mentor Status Checker's run; the mail log of the day; the feed's answer;
two schools' rosters; and **rows changed in the base since the switch** (I14): the base's owner reads the Students
Reports rows whose "Student report last edited" is after 00:00 UTC on 28 December and the base's revision history of the other three
tables, read-only; each one found is typed onto the site by an Administrator.

## 17. What "done in December" means

After 28 December the site keeps every student's and mentor's record, and nothing reads the base about a student or a
mentor. One tie is left: institutions. An approved application and a promoted lead still create an Institutions row to
get the school's key (8.8), and the institutions' copy goes on writing each school's columns there. The base therefore
stays connected, written to and never read, for institutions alone. Two honest options, for the product owner:

The work is the same nine tasks either way:

1. `WPCPM_Institution_Keys` (**new**): the site-made institution key (`ins` and 14), minting, the shape test.
2. The source-check suite listing every institution key test (about 40 files), in 3.9's manner.
3. The institution key tests of the institutions module's screens and handlers moved to the new test (first half).
4. The same, second half (the roster, the semester report, the abilities, the sponsors' links).
5. An approved application makes a site key, with no Airtable row (`WPCPM_Institution_Approval`).
6. A promoted lead makes a site key (`WPCPM_Institution_Leads`, no `WPCPM_Institution_Copy::create()`).
7. The copy stopped behind its own setting; its hourly retry unscheduled; the notice gone.
8. The outside readers' Institutions reads onto the feed (the public dashboard script, the WPCredits Tracker).
9. Guides, readme, version.

- **(a) Cut them in a small follow-up in January**, the nine tasks by one stream. Meanwhile the base is frozen for students and mentors and
  readable, and its Institutions table goes on receiving the copy. **This is what the plan as written assumes.**
- **(b) Cut them before 28 December.** The same nine tasks in stream B from Monday 7 December, merged into release 2. Stream
  B's slack (about nine days) holds them, but the slip list then starts at its first item as soon as stream B slips a
  day, and release 2 carries a change to every institution screen a week before the switch. Then on 28 December nothing
  reads or writes the base, and it can be switched off.

## Corrections to the inventory

- **The shadow fill cannot live inside the syncs' existing phases** (inventory 6.2): the students sync reads Students
  Reports only at the tracked statuses (`phase_reports()`, `modules/class-wpcpm-students-sync.php:887`) and asks for
  none of the report answers. The fill is a new phase (6.1).
- **The site's own screenshots are kept per account, not per enrollment** (inventory 1.2 and 5): `_wpcpm_report_images`
  is keyed by column on the account. The fill copies them onto enrollments (7.2).
- **Most key tests are the alias `WPCPM_Mentors_Sync::is_record_id()`**, and the REST route takes any letters and
  digits, which is why the site key has no separator (3.8).
- **There are two student keys:** the account and the mentors' notes and calls use the Students Reports ID, the roster
  index's rows the Students record ID (`WPCPM_Roster_Index::KEYS`), and no link pairs the two rows reliably (3.2).
- **The told set moves with its keys unchanged** (`WPCPM_Mentor_Assigned::OPT_TOLD`, 7.3).
- **The institutions copy does not simply stop when the students move** (inventory 6.3): two paths still make a key in
  the base (8.8, section 17).
- **The column fixtures are older than the base** (Students Reports 74 fields against 104, Students 29 against 33) and
  hold no Dropped out reason (14.1).
- **The Students table's Status has more choices than the counts show:** Interested, In Sensei Self-onboarding,
  Duplicated and a cohort's name, besides those counted (3.3).
- **Only Students Reports has a last-modified field** ("Student report last edited", `lastModifiedTime`); Students,
  Feedback and Mentors have none (8.3, condition 4).
- **A track welcome needs a mentor today** (the automations' trigger), while the standing word is that a mentor is
  optional (10.1).
- **The syncs make and revoke accounts today**; with them stopped something must do it on each move (9.6).
- **The inventory's dates (its section 10 and decision 4) are superseded** by the product owner's word of 20 November
  2026: the switch is in December.
