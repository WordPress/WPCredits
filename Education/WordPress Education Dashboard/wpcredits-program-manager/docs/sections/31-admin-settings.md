## Settings

**WPCredits Program > Settings**. Seven tabs along the top of the screen, in WordPress's own tab bar:
**Connection**, **Students and mentors**, **Institutions**, **Sponsors**, **Security**, **Mail** and
**Advanced**, and the screen opens on Connection. Each tab but Mail is a form of its own with its own
**Save settings** button: saving one tab leaves every other tab exactly as it was, and the notice
after a save names the tab it saved ("Sponsors settings saved."). A page left open from before an
update can post a form that names no tab; it saves nothing, and the notice asks you to reload the
page and save again.

Each section of a tab opens with one sentence and a link to its part of this guide, on a site that
publishes the guide at /program-manager-guide/. Each setting has one sentence of help under it, and
the rest of what there is to know in a **Details** fold under that, closed until you open it.

A setting that holds something Airtable or the site can list is chosen rather than typed: the base,
the tables and their name columns, the statuses and the stages, and the program managers who are
told about agreements, reports and sponsors' mail. When a list cannot be read, the setting is typed
as it always was, and a sentence under it says why. A value saved that its list does not hold is
still offered, after the list and marked *(current)*, so a Save never drops it.

Three tools keep settings of their own, the **Mentor Status Checker**, the **Student Duplicate Finder**
and **Need help?**, and each keeps them on its own screen, in a **Settings** section at the top with a
Save of its own, rather than on this one. The Connection tab's first line names the three, each a
link to its Settings section, and the next part of this guide, on the tools, says what each of those
settings does.

### The Connection tab

Its first line names the three tools that keep their settings on their own screens. Then two
sections, **Airtable** and **Tables**, the tab's **Save settings**, and under it a button of its own,
**Read the lists from Airtable again**.

#### Airtable

| Setting | What it is for |
| --- | --- |
| **Personal Access Token** | Stored in the database and never sent back to the browser: the field shows it masked. Leave it blank to keep the current token. |
| **Schema token** | Optional, and used by the Track Builder alone, to create a track's columns when it is published. Its fold says the rest: it needs the `schema.bases:write` scope and must belong to somebody with the base creator role on the base; left blank it keeps the current one, and typing remove takes it away. Without it, publishing lists the columns for somebody to create by hand. |
| **Token scopes** | The scopes the token needs, listed in the row's fold with what each is for: `data.records:read`, required, for reading mentors, students and tutors; `data.records:write`, required by the Mentor Status Checker, for changing a mentor's status when you promote them (without it the tool can still run in report-only mode, but promoting fails); `schema.bases:read`, optional, for listing the bases, tables, columns, statuses and stages the Settings tabs and the Mentor Status Checker's screen offer, and reading each column's description from Airtable (without it each of those settings is typed, and the built-in descriptions are shown instead). Set them on the token itself at airtable.com/create/tokens, with access to the WPCredits base; they cannot be checked from here without writing to the base, so the list is the reference. |
| **Base ID** | The Airtable base holding the program, chosen from the bases the token can open, each with its ID beside its name. Listing the bases needs the token's `schema.bases:read` scope; when the list cannot be read, the ID is typed, and a sentence under the field says why. |

#### Tables

Each table of the base the plugin reads or writes, chosen from the base's tables with its ID beside
its name, and each name column from its own table's columns. When a list cannot be read, the table's
ID or the column's name is typed, and a sentence under the field says why: no Personal Access Token
or no Base ID is set, Airtable refused the token, the token lacks the `schema.bases:read` scope or
access to the base, the base has no such table or column, or the read failed.

Six of these had no control before 1.117.0 and could only be changed in code: the **Tutors**,
**Feedback**, **Countries** and **Sponsors** tables, and the **Countries** and **Sponsors** name
columns.

The bases, statuses and stages are read from Airtable once a day, the tables and columns every
fifteen minutes, and all of them again after a Save of the Connection tab. A read that failed is not
asked again for five minutes, so an Airtable that does not answer holds up one draw of a tab, not
every one, and the fields stay typed meanwhile. **Read the lists from Airtable again**, under the
tab's Save button, reads them at once: for a status or a stage somebody has just added in Airtable,
which cannot be chosen until the lists are read again, or once Airtable answers again.

When its table is chosen from the list, which saves the table's ID, each of the **Institutions**,
**Countries** and **Sponsors** name columns can also be set to **None (the built-in column)**, which
leaves the setting blank: each is then read from the column the plugin names by default, **Name**,
**Name** and **Company Name**, and by the mentors sync from the table's primary column, which it finds
by the table's ID. A table saved by its name, as one typed before 1.117.0 could be, keeps its name
column typed: the mentors sync would ask for that column itself, and a blank one names no column.

| Setting | What it is for |
| --- | --- |
| **Mentors table** | Where mentor records live. |
| **Students Reports table** | Holds the internship dates, links and contribution team the mentor page shows. |
| **Students table** | Read only for the Tutor column, which does not exist on Students Reports. |
| **Institutions table** and **Institutions name column** | Airtable sends linked-record fields as record IDs rather than names, so the table is read to turn those IDs into names. The name column is used only when the token lacks the `schema.bases:read` scope; with that scope the primary column is detected automatically. |
| **Contribution areas table** and **Contribution areas name column** | Read the same way, for the contribution team a record links to. |
| **Tutors table** | Read when an institution enrolls students, for that institution's own tutors and no other school's. |
| **Feedback table** | Where the students' survey answers are written: one row per student, with a column per question. |
| **Countries table** and **Countries name column** | Read for the name of each institution's country and the program manager who looks after it; the name column holds each country's name. |
| **Team Members table** | Read by the sponsors sync for each sponsor's program contact. Not the Contribution areas table. |
| **Sponsors table** and **Sponsors name column** | Read by the sponsors sync, and written to whenever a sponsor's record changes on this site, from an approved application to a new logo; the name column holds each company's name, which the mentors sync reads too, for the report form's company field. |

### The Students and mentors tab

Four sections: **Who is on the program**, **When someone leaves**, **Accounts** and **Landing
pages**. The status lists are shared: a current student is anyone a mentor is currently mentoring.

#### Who is on the program

- **Currently mentoring** - a checkbox list of statuses: the Students Reports table's Status options
  under **From Airtable**, and the status of every track the site runs under **From the Track
  Builder**, each once. Students holding any of the ticked statuses appear under "Currently
  mentoring" on their mentor's page. The list keeps the status of every track the site runs: its box
  is ticked and switched off, with a note naming the track, and a save that would take one out all
  the same saves everything else, leaves Currently mentoring as it was, and a notice names the track.
  Take the track off the live site in the Track Builder first, then remove its status. A Settings page
  left open while a track was published keeps the new status when it is saved, and the Track
  Builder's list flags a live track whose status is missing from Currently mentoring. Left untouched,
  the list saves as it is stored; changed on a page left open, it brings back a status somebody
  removed in the meantime, so reload the page first. When Airtable's statuses cannot be read, the
  list is a box to type in, one status per line, and a sentence under it says why.
- **Past students** - statuses that mean mentoring has finished, from the same two lists. Those
  students appear in a separate, collapsed section on their mentor's page. Its fold: with none
  ticked, only current students are shown, and a status in both lists counts as current. No track
  runs on a past status, so the list never takes the status of a track the site runs: its box is
  switched off, under one note for the group, "A live track's status cannot be a past status.", and a
  save that would add one all the same saves everything else, leaves Past students as it was, and a
  notice names the track. Take the track off the live site in the Track Builder first, then add its
  status. A status the list already holds is never switched off there, so it can always be taken out.
  The program's four original tracks always run, so their statuses are never past statuses.
- **Mentor status to sync** - chosen from the Mentors table's Status options, *Active* by default;
  only mentors holding this status get an account.

Neither Currently mentoring nor Mentor status to sync can be left blank: each is what a sync filters
the base by, so a blank one goes back to its default and a notice says so.

#### When someone leaves

What the sync does with the account of a mentor or a student who is no longer on the program. The
answer that removes comes first, and it is the default for both.

- **When a mentor is no longer active** - **Remove the Mentor role and clear their student list**,
  or **Leave the role in place**. The account itself is never deleted either way.
- **When a student leaves the program** - **Remove the Student role, so they lose access to
  Student-level content**, or **Leave the role in place**. The account itself is never deleted
  either way, and their program details are kept.

#### Accounts

- **Invitation emails** - **Email each new mentor and student a password-reset link as their
  account is created**. Off by default, and worth leaving off unless you mean to email everybody: a
  first sync creates around ninety accounts at once. Its fold: invitations are queued and sent a few
  at a time rather than all inside the sync, so a mail limit cannot swallow half of them unnoticed,
  and you can also invite people one at a time from the Mentors and Students screens, or tick
  several in the Students or Mentors screen's list and invite them together.
- **Automatic sync** - **Read Airtable on a schedule**, on by default: the students, mentors and
  institutions syncs every three hours, the mentors run half an hour after the students run. The
  sponsors sync is on the same three-hour clock and runs regardless of this switch. Its fold: the
  student rows carry what people are shown on their cards, and the students run is the expensive
  one, reading a WordPress.org profile per mentor, cached for twelve hours; the mentors run reads
  three Airtable tables and no WordPress.org profile. A run already in progress is left to finish
  rather than restarted. The students and mentors runs can also be run by hand from the Students and
  Mentors screens.

#### Landing pages

Where mentors and students go when they log in. Each switch has the page's address under it, or,
when the page is missing, a sentence saying that re-activating the plugin recreates it.

- **Mentor landing page** - **Use the Mentor Report Card page as the mentor dashboard**, on by
  default: mentors go to their Mentor Report Card when they log in and in place of the wp-admin
  Dashboard, and get a "Mentor Report Card" link in the toolbar. Its fold: they keep their own
  profile screen, a mentor who followed a link somewhere specific still lands there, and
  administrators are unaffected.
- **Student landing page** - **Use the Student Report Card page as the student dashboard**, on by
  default: the same arrangement for students, with a "Student Report Card" link in the toolbar and
  the same exceptions: a requested destination wins, their own profile screen stays reachable, and
  anyone who can write posts is left alone.

### The Institutions tab

Five sections, headed on the screen **Applications and enrollment**, **Landing page**, **When an
institution leaves the pipeline**, **Collaboration Agreements** and **Semester reports**.

#### Institution applications and enrollment

- **Applications from institutions** - **Take applications through the form on this site**. Off by
  default, since the form is a public page anybody can post to; its address is under the switch.
  Every submission is stored for a program manager to read on the Institutions screen's Waiting for
  review tab and to decide on the Administrator Dashboard, and nothing is created until somebody
  approves it; while this is off, the page shows one sentence saying applications are closed. The
  form shows nothing to the public without a published privacy policy, however this is switched, and
  the row says so until one is chosen under Settings > Privacy.
- **Enrollment lists from institutions** - **Let an institution send a list of students to
  enroll**. Adds an "Enroll students" section to the Institution Dashboard, where a school chooses
  the program and the term and then adds one student or sends a CSV. Off by default. Its fold: the
  list is read and checked against the program records, and the school sees what was understood
  before anything is created; while this is off, the section does not appear at all. Each
  institution is held to five checks an hour and 600 students a day, and the files are read and
  never stored.
- **Create accounts automatically** - **Let the sync create the first account for a Confirmed
  institution**, from the Contact Email Airtable holds, and only for a Confirmed institution whose
  agreement is recorded and that has never had a member. Off by default. Its fold: an address that
  already belongs to an account is left alone, and on the Institutions screen's Accounts tab, under
  No account, the institution is listed with the reason, never the address or the account; with
  this off, accounts are created only when somebody presses Create account there.

#### Institution landing page

- **Institution landing page** - **Use the Institution Dashboard page as the institution
  dashboard**, on by default: institution accounts go to their Institution Dashboard when they log in
  and in place of the wp-admin Dashboard, and get an "Institution Dashboard" link in the toolbar, with
  the same exceptions as for mentors and students. The page's address is under the switch.

#### When an institution leaves the pipeline

- **Its people** - **Remove its people, so they lose access to its students**, the default, or
  **Leave their access in place**. The accounts themselves are never deleted either way, and the
  agreement and the roster are kept. Its fold: an institution leaves the pipeline when Airtable moves
  it out of the stages the program treats as active, which the Advanced tab lists under **Pipeline
  stages**.

#### Collaboration Agreements

- **Agreement review** - how many days, from 1 to 60 and 3 by default, a signed agreement may wait
  before the queue marks it overdue and the nightly digest names it.
- **Who reviews agreements** - who is told when an agreement arrives and sent the overdue digest: a
  box for each program manager, under **Administrators**, and an **Other addresses** line for
  anybody else, separated by commas. With none, every program manager is written to, which reaches
  technical administrators as well, so set it before the first real upload. Where no program manager
  account with an email address can be listed, the addresses are typed, separated by commas.
- **The agreement wording** - the Google Doc the plugin's copy of the Collaboration Agreement was
  taken from, used by the Check against the Doc button; Google addresses only. Its fold: it is kept
  on this site rather than in the code, because the document is editable by anyone holding its link
  and the plugin's source is public.

#### Semester report drafting

- **Drafting** - **Draft each institution's semester report when the semester ends**, on by
  default: a daily job drafts a report for every finished semester and tells the program managers.
  Off, drafts are written only when a manager presses Draft now.
- **Drafting grace** - how many days after a semester ends, from 7 to 365 and 45 by default, the job
  waits for students still in progress before drafting anyway. The draft says how many were still
  in progress.
- **Who reviews reports** - who is told when the job drafts a report, chosen as the agreements'
  reviewers are. With none, every program manager is written to.

### The Sponsors tab

Five sections, headed on the screen **Applications**, **Landing page**, **When a sponsor is no longer
Approved**, **Interest mail** and **Offers**.

#### Sponsor applications

- **Applications from sponsors** - **Take sponsor applications through the form on this site**, at
  /sponsor-application/. Off by default, since it is a public page anybody can post to; its address
  is under the switch. Every submission is stored for a program manager to read on the Sponsors
  screen and on the Administrator Dashboard, and nothing is created in Airtable until somebody
  approves it; while this is off, the page shows one sentence saying applications are closed. As
  with institutions, the form shows nothing to the public without a published privacy policy, and
  the row says so.

#### Sponsor landing page

- **Sponsor landing page** - **Send sponsor accounts to the Sponsor Dashboard when they log in**,
  instead of the wp-admin Dashboard, on by default. Accounts that can also edit content or manage
  the program are left where WordPress sends them. The page's address is under the switch.

#### When a sponsor is no longer Approved

- **Its accounts** - **Remove its accounts from the sponsor, so they lose access to its Sponsor
  Dashboard**, or **Leave their access in place**, the default. Nothing is ever deleted, and Paused
  and Not Moving Forward sponsors keep their accounts by default, because a pause is often short.
  Its fold: Airtable's Status is the record, and the first answer has the sync remove the accounts
  from any sponsor whose Status is no longer Approved.

#### Interest mail

- **Who is mailed** - the addresses mailed in place of a sponsor's program manager when it has
  none, chosen as the Institutions tab's reviewers are; with none here, every program manager is
  written to. Every mail meant for a sponsor's program manager goes to its assigned program manager:
  its interests, a mentor it would like to sponsor, its signed agreement, a problem reported with one
  of its codes and a warning that its codes are running low. A sponsor with none is mailed here, and
  every new sponsor application comes here too, since a new application has no manager yet.

#### Offers

- **Largest logo** - the largest logo file, in KB, from 100 to 8192 and 1024 by default, for the
  logos the sync copies from Airtable, the ones sponsors upload and the ones sent with an
  application. PNG, JPEG and WebP only; never SVG.
- **Tools from our sponsors** - two boxes: **Show the section on the Student Report Card**, on by
  default, and **Show the section on the Mentor Report Card**, off by default. The section lists the
  live offers, each with its claim button. Its fold: a student always sees offers open to students,
  a mentor sees the offers whose sponsor opened them to mentors, and the Administrator Dashboard
  shows every live offer whatever these say.
- **Low-stock warning** - when a pool of one-time codes falls below this many codes, from 1 to 1000
  and 10 by default, the sponsor and its program manager are mailed, once per crossing. Its fold:
  this is the default for new offers, and each offer can set its own.

### The Security tab

One section, **Two-factor authentication**.

#### Two-factor authentication

- **Roles that must use it** - a box for each role: **Administrators**, then Student, Mentor,
  Institution and Sponsor. An account in a ticked role is asked for a code as well as its password
  from its next sign-in, with nothing to set up first: the code is emailed. Administrators and
  institutions by default. Its fold: each person can set up an authenticator app on their own
  profile screen, which is quicker and does not depend on their email, and unticking everything asks
  nobody. Students are left off by default: a student account holds that student's own work, there
  are hundreds of them, and there is nobody to unlock the ones who change phone. They can still turn
  it on for themselves. Without the Two Factor plugin active nobody is asked, and the row says so
  above its boxes.
- **Where it stands** - while the Two Factor plugin is active, for each ticked role, how many
  accounts are covered and how many use an authenticator app, counted when the tab is opened. An
  account that is covered but has no app is using emailed codes.

### The Mail tab

Two sections, headed on the screen **Invitations** and **Recent mail**. No settings and no Save: each
button is a form of its own.

#### Sending yourself an invitation

The student, mentor, institution and sponsor invitations say different things, so you can send
yourself any of the four before real people read it, each with a button of its own: **Email me the
student invitation** and its three siblings. The sample goes to your own address. Above the
buttons, how many invitations are still waiting to be sent, when any are; they go out a few at a
time in the background.

#### Recent mail

A log of the last 25 messages the plugin sent: bookings, cancellations, reminders and invitations,
each with when, to whom, its subject and what it was, and whether the site accepted it. "Accepted"
means the site handed the message off without complaint; it cannot tell you the message was
delivered or read. A message the site refused is marked **Refused**, and a line above the log counts
them: that is a delivery problem to fix, not a program one.

### The Advanced tab

Eleven settings that change rarely, and whose defaults suit almost every site, in four sections,
headed on the screen **Pipeline stages**, **Applications**, **Agreements** and **Invitations**. Until
1.117.0 they could only be changed in code. Each number field refuses what the save would not keep,
so the limits given below are the field's own.

#### Pipeline stages

- **Institution starting stage** - the Current Stage an institution is created at in Airtable when
  its application is approved, *First Contact Made* by default, chosen from the Institutions table's
  Current Stage options. It cannot be left blank: a blank one goes back to its default, and a notice
  says so.
- **Institution pipeline stages** - a checkbox list of the same options, eight of them ticked by
  default, from *First Contact Made* to *Student*. An institution at any of the ticked stages is in
  the pipeline, and one whose stage leaves the list is treated as having left it, which the
  Institutions tab's leaving rule decides the rest of. It cannot be left empty, since an empty list
  would count every institution as having left: an empty one goes back to its default, and a notice
  says so. When the stages cannot be read, both are typed, the pipeline stages one per line, and a
  sentence under each says why.

#### Keeping applications

How long a decided application is kept, institution and sponsor applications alike, and the one
proxy the two application forms believe.

- **Spam kept for (days)** - from 1 to 365, 30 by default: an application marked as spam is deleted
  this many days after it was marked.
- **Rejected kept for (days)** - from 30 to 3650, a year by default: a rejected application, or one
  the checks held, is deleted this many days after the decision, which is long enough to recognize
  the same applicant applying again.
- **Approved kept for (days)** - from 0 to 3650, 0 by default, which keeps approved applications
  forever: they are the record of who was let in.
- **Trusted proxy** - the one IP address, not a range, of the proxy whose forwarded header the
  application forms believe for a sender's address, which their limits count by. Its fold: leave it
  empty unless the site sits behind a proxy, since anybody can send that header; anything but one
  address, a range included, is saved as empty.

#### Agreement files

- **Largest upload (MB)** - from 1 to 50, 10 by default: the largest signed agreement an institution
  or a sponsor can upload.
- **Uploads per day** - from 1 to 50, 5 by default: how many files one institution or one sponsor
  can upload in a day.
- **Generations per day** - from 1 to 100, 10 by default: how many times one institution can
  generate its agreement from the template in a day.
- **Discarded files kept for (days)** - from 7 to 365, 30 by default: a withdrawn or returned file is
  deleted this many days after it was set aside. An accepted agreement never is.

#### Lapsed invitations

- **Settled invitations kept for (days)** - from 7 to 365, 30 by default: how long an invitation to
  join an institution's account stays listed once it was accepted, canceled or ran out, so a program
  manager can still see who was invited and never came.
