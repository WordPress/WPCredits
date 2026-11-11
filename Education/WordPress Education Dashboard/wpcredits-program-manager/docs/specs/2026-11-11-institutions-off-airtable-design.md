# Institutions off Airtable (design, 11 November 2026)

Plugin: WPCredits Program Manager. Live when this was written: 1.123.77. The first of the three moves of the
Airtable exit (institutions, then mentors, then students, all by 15 December 2026). Inventory:
`~/Obsidian/WordPress Education/WordPress Education Dashboard/Airtable inventory (11 November 2026).md`. Model and
storage background: the vault note "Students in several programs, and leaving Airtable (8 October 2026)". The CRM split:
the vault note "Education pilot and the Directory hand-off (October 2026)".

## 1. Why

Institutions already apply, are approved, sign their agreement, correct their details and enroll students on the site,
but the record is still the Airtable Institutions table. The site reads it every three hours, and Administrators still
work the talks with each school in the Airtable grid: the stage, the contact people, the country, the estimated number
of students, when the first students begin, and the follow-ups. Until that work has a home on the site, Airtable cannot
be left. Institutions move first because the site already holds most of their life.

## 2. The product owner's decisions (11 November 2026)

1. **The site becomes the record for institutions.** All of them, with their talks and follow-ups.
2. **Where Administrators work: a Talks view and a Talks card, together.** The Talks view on the Administrator Dashboard
   shows what needs attention across all schools; the Talks card on each school's own page is where one school's talks
   are changed (options B and C of the mockups).
3. **What the Talks card holds:** the stage of the talks, the contact people, the country, the estimated number of
   students, **when the first students begin** (a date Airtable never held), and the follow-ups: next step, follow-up
   date and notes.
4. **A weekly email** lists each Administrator's follow-ups that are due.
5. **Areas of responsibility.** Each Administrator is given countries, regions or continents. A school that applies from
   an area is assigned to that Administrator automatically, and the Administrator is notified.
6. **A Tutor role** for school accounts, which sees all the school's students (the product owner asked whether there
   should be one; this design proposes it, section 8).
7. **Leads stay on the site until the CRM is live.** The Outreach Tracker's leads and the talks before an application are
   held on the site; when the shared CRM goes live they are handed to it, and the line of 28 October 2026 (the CRM owns
   the talks before the application, the site from Under Review on) applies from then.
8. **New leads are added on the site** after the switch: an Administrator adds one from the Talks view ("Add a lead"),
   and the Airtable source behind the Outreach Tracker stops being used on switch day. Anyone who finds leads and is not
   an Administrator passes them to one.
9. **The leads' import brings everything** the Outreach Tracker holds (name, city, country, contact people, notes, last
   contact, lead owner, help desk link), seen by Administrators only. A lead whose owner is an Administrator is assigned
   to that Administrator; the rest are assigned by area (section 7).

The rest is the author's account of how, marked as the author's call where it is one.

## 3. The record on the site

**One record per institution, kept by the site and written per change.** It holds every Institutions column the program
still uses (inventory, Institutions row) plus the new fields of section 4. Today the site's list of institutions is
`WPCPM_Institutions_Index`, an option the sync rewrites whole from Airtable, beside `WPCPM_Institution_Record` (the
site-only answers). After the move the index is no longer a copy: it is written by the site's own writes and never by a
read of Airtable.

**The key stays the Airtable record ID** (`rec...`), as it did for sponsors: every member link, agreement, request, audit
row and the CRM's company link already use it. A school created on the site after the move gets a key of the same shape
from its Airtable copy (section 9), so one key works everywhere until the students move.

**Storage is the builder's, under one constraint** (from the 8 October note): the site's database connects in latin1. A
custom table in utf8mb4 written over that connection stores double-encoded text, so whichever storage is chosen must store
names in every script correctly, proven on the Docker copy with names in Polish, Romanian, Arabic and Japanese.

### 3.1 Which Airtable columns come over (the author's call)

Every column a screen, a form or the copy (section 9) uses, plus the talks: **Next Step** becomes the card's next step,
**Follow-Up Date** its follow-up date, **Last Contact Date** is kept and shown, **Notes** becomes the first note (dated the
import day, "Imported from Airtable"), and each Airtable **record comment** on the row (read through the comments API on
the day) becomes a note with its own date and author's name. **Original program manager** seeds the assignment when no
area matches. **Department**, **Student Contact name** and the three application answers (internships, why, anything
else) stay on the record as the application's answers, read-only. **To Follow Up** and **Days to Follow-Up** (the old
follow-up automation's inputs) and the link and lookup columns that belong to other tables (Students, Mentors, Feedback,
Students Reports, the Countries lookups) do not come over: the links stay in Airtable until their tables move. The full
list of the 45 columns with each one's fate is the plan's first table.

**History.** Every change to a school's stage, assignment, dates or follow-ups is a row in the institution's history
(`WPCPM_Institution_Audit`), with who and when, as details corrections are today.

## 4. Stages

The site keeps Airtable's stage names, so the settings that name stages (`institution_active_stages`, the registration
form's offered stages, the Pipeline's uncounted stages) keep working: First Contact Made, Info Sent, Call Scheduled,
Waiting on Reply, Under Review, Agreement Sent, Confirmed, Revisit Later, Not Moving Forward, Student, SPAM, plus one new
stage for decision 7, **Lead**, before First Contact Made.

- **Lead** is a school the program has not talked to yet, added on the Talks view or imported from the Outreach Tracker,
  with its city, country, contact people, notes, last contact, lead owner and help desk link. It is never counted, never on a public page, never offered by
  any form, never written to Airtable (section 9), and it has no accounts.
- **Confirmed** is still set by the agreement path (accepted, or recorded as on file), never by hand on the Talks card: the
  agreement is the gate. The card offers every other stage.
- **Leaving an active stage revokes the school's accounts**, as the sync does today (`institution_on_inactive`). The card
  says so before it saves such a change, and the school's members are told as they are today.
- **Revisit Later** proposes a follow-up date 90 days ahead (the author's call; Airtable's automation set one), which the
  Administrator may change.
- **Student** (7 schools today) are schools a student named in the old student interest form; they keep the stage and an
  Administrator moves them on.

## 5. The Talks card (on each school's page)

On the school's own page, reached by Administrators through the dashboard's switcher, as the details card is. Shown to
Administrators only; a school member never sees it, and the policy refuses a member's post (`WPCPM_Institution_Policy`,
a manager ground, as Tracks offered does).

It holds: **stage** (with the history line of the last change), **assigned Administrator** (one field to type and pick),
**contact people** (from the details card, shown here, changed there), **country**, **estimated students** (prefilled from
the application's answer, editable), **first students begin** (a date), **next step** (one line), **follow up on** (a
date, marked when past), and **notes** (dated entries with their author, newest first, never edited, only added). One
Save for the fields; a note is added on its own. It is drawn in the card shell of the dashboard frame redesign
(`wpcpm-dashboard-frame-design`), and follows the Institution Dashboard's rules (Dashboards look alike).

## 6. The Talks view (on the Administrator Dashboard)

A view in the Administrator Dashboard's side menu, "Talks", in the frame that is live. It opens with one sentence in the
dashboard's voice (how many follow-ups are due and the oldest), then **Follow-ups due** (school, next step, due date,
oldest first), then the schools **by stage**, one column or group per stage from Lead to Confirmed with a count, the
closed stages (Revisit Later, Not Moving Forward, SPAM) folded. A filter **Mine / All** (mine by assignment, the default
for an Administrator with an area) and a search by name. Each school opens its page at the Talks card. The mockup the
product owner chose: `.superpowers/brainstorm/24643-1794375701/content/talks-home.html`, options B and C.

### 6.1 The weekly email

Every Monday (the author's call: 07:00 UTC, beside the quiet students' Monday email), each Administrator with at least one
follow-up due among the schools assigned to them gets one email: the schools, each with its next step, its due date and a
link to its Talks card, oldest first. Due follow-ups of unassigned schools are listed in every Administrator's email under
their own heading. Nothing is sent to an Administrator with nothing due. A template in Tools > Emails, area Institutions.

## 7. Areas of responsibility and assignment

**Who is an Administrator here** (the author's call): an account with the plugin's manage capability, the program's
Administrators the site already knows, never every WordPress administrator account. Only they appear in the areas, as
assignees, in the notices and in the weekly email.

**Areas.** A setting maps each Administrator to countries and to regions. The regions (the author's call): Africa, Asia,
Europe, Latin America and the Caribbean, Middle East, North America, Oceania; each country belongs to one region, the
mapping kept in the plugin and editable. The most specific match wins: a country's Administrator before the region's.
One Administrator per country and per region (a save that gives one twice is refused and says which).

**Assignment.** Every institution has an assigned Administrator. A new application, a new lead and the import set it from
the areas; a school with no matching area is unassigned and every Administrator is told, as today. An Administrator can
reassign on the Talks card. The assigned Administrator is notified by email ("A new institution is assigned to you"),
on assignment and on reassignment, from a template in Tools > Emails, area Institutions.

**This replaces** the Countries table's "Person of contact (Team)" and the Team Members table: the booking link each
Team Member has becomes a field on the Administrator's own account, and the application's acknowledgement offers the
assigned Administrator's booking link (today it is the country's, `WPCPM_Countries::routing()`).

**Before the switch (R1, the author's call):** the areas are seeded once from the Countries table's "Person of contact
(Team)", the site routes new applications by its own areas from then on and later edits of that column are ignored, and
the Airtable automation "New institution for one Administrator" is switched off in the same release, so nobody gets the
news twice.

## 8. The Tutor role (proposed)

Today every account linked to a school sees all its students and may also add students, change a student's status, resend
invitations and correct the school's details (every `ACT_*` of the policy allows the member ground). A tutor needs to see,
not change. So a school account gets a **level**: **Representative** (today's member, the default for every existing
account) or **Tutor**. A tutor sees the roster, each student's page and Student Report Card, the school's semester
reports, and changes nothing: the policy allows a tutor only the view actions, and every handler already asks it. A
representative or an Administrator invites a tutor from the People card (the invitation says which level). Each school
keeps **one list of its tutors** on the site (name and email, the author's call), and a tutor in the list may have an
account at the Tutor level once invited; the list is what the school's student import offers in its tutor picker (today
`WPCPM_Institution_Import::tutors()` reads Airtable's Tutors table live). The 14 tutors in Airtable (they have emails) are
imported into their schools' lists at the switch; inviting them is a separate step, only on the product owner's yes, since
that mails real people.

## 9. Airtable keeps a copy, written by the site, until the students move

Students and mentors stay in Airtable until December, and their rows link to Institutions rows; the WPCredits Tracker, the
Credits Monthly Report and the dashboard script read the Institutions table too. So after the switch the site **writes**
a copy of each institution to the Airtable Institutions table on every change (name, stage, country, city, website,
contact person and email, confirmed on, the agreement columns it writes today), and **never reads it back**. A school
created on the site is created there too, to give it its key. Leads are never written. A write that fails is retried, as
the registrations' are, and named on the Institutions screen until it goes.

**A way back** (the author's call): the copy also writes the talks columns Airtable already has (Next Step, Follow-Up Date,
Last Contact Date), and the sync's institutions phase stays in the code behind a setting, off after the switch, until the
students move. Going back would lose only the notes added after the switch, the first-students date and the assignment. When the students move, the copy stops and
the table is archived; the readers then read a feed from the site.

## 10. The six Airtable institution automations

Switched off at the switch, each replaced or retired: **Application Received** (the Airtable interest form's
acknowledgement) goes with that form, which closes after the Institutions testing (#292); **Revisit Later follow-up
date** becomes section 4's proposal; **Institution confirmation date** becomes a stamp the site writes when a school is
Confirmed; **New institution for one Administrator** becomes the assignment email; **Student status in institutions
table** is retired with the old student interest form; **Promote outreach lead** becomes a stage change from Lead on the
Talks card.

## 11. The switch

1. Rehearsal on the Docker copy: the import, the Talks view and card, the copy written to a stand-in, the readers.
2. On the day: the Institutions table and the Outreach Tracker frozen (a note to the Administrators), a last read, the
   import (all institutions with notes, comments and follow-ups; the Outreach Tracker read fresh that day, its leads not
   yet promoted as Lead with everything decision 9 names, its promoted ones matched to their institutions by name, the
   way the export in `~/Projects/wpcredits-airtable-exit` already matched 20), no assignment email sent by the import, the areas from the
   Countries table, the booking links from Team Members, the tutors; the sync's institutions phase off; the copy on; the
   six automations off.
3. Read back on live; the Administrators told where their talks are now.

Only the product owner's yes starts step 2.

## 12. Goals, non-goals, anti-goals

**Goals:** Administrators run every school's talks on the site; nobody opens the Airtable Institutions grid after the
switch; students and mentors in Airtable keep working until their own moves.

**Non-goals:** moving mentors or students; the CRM sync (built later, against the site); the Campus Connect base; public
pages; a school seeing its own talks.

**Anti-goals (a failure even if it works):**
- an institution changed in two places, or the site reading Airtable after the switch;
- a school losing access because of the switch itself;
- a student's or mentor's link to an institution broken in Airtable;
- a lead counted, shown publicly, offered by a form or written to Airtable;
- an email to a school, a tutor or a lead that no Administrator chose to send;
- a note or a follow-up lost in the import;
- names stored double-encoded.

## 13. Releases and dates

The builder may slice it; the author's proposal: **R1**, areas and assignment, the assignment email, the Tutor level (they
work while Airtable is still the record). **R2**, the record on the site, the Talks card and view, the weekly email, the
copy and the import, shipped and then switched on the product owner's yes. Built after the #292 reports (20 November),
switched in the first week of December, so mentors and students can follow by 15 December. The Administrator guide and
the Institution guide document every screen and option in the release that brings it.

## 14. Left to the builder

The storage (within section 3's constraint), the record's fields' names, the Talks view's layout within the frame, the
email's words, the region mapping's form, the retry's schedule, how the copy's failures are shown.

## 15. Open questions

None for the design. The education leads' three questions (due 20 November) do not change it: this design answers the
first one for institutions (the Talks view replaces the grid).
