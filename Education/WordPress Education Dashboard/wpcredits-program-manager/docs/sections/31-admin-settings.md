## Settings

**WPCredits Program > Settings**. Seven tabs along the top of the screen, in WordPress's own tab bar:
**Connection**, **Students and mentors**, **Institutions**, **Sponsors**, **Security**, **Mail** and
**Advanced**, and the screen opens on Connection. Each tab but Mail is a form of its own with its own
**Save settings** button: saving one tab leaves every other tab exactly as it was, and the notice
after a save names the tab it saved ("Sponsors settings saved."). A page left open from before an
update can post a form that names no tab; it saves nothing, and the notice asks you to reload the
page and save again.

Each section of a tab opens with one sentence and a link to its part of this guide, on a site that
publishes the guide at /administrator-guide/. Since 1.123.41 that is the guide's address, moved once
from /program-manager-guide/ on the first request after the update, and /program-manager-guide/, its
address before, takes a visitor there. Each setting has one sentence of help under it, and
the rest of what there is to know in a **Details** fold under that, closed until you open it.

A setting that holds something Airtable or the site can list is chosen rather than typed: the base,
the tables and their name columns, the statuses and the stages, and the Administrators who are
told about agreements, reports and sponsors' mail. When a list cannot be read, the setting is typed
as it always was, and a sentence under it says why. A value saved that its list does not hold is
still offered, after the list and marked *(current)*, so a Save never drops it.

Four tools keep settings of their own, the **Mentor Status Checker**, the **Student Duplicate Finder**,
**Need help?** and **Emails**, and each keeps them on its own screen, in a **Settings** section with a
Save of its own, rather than on this one: at the top of the screen, or for Emails at the top of its
**Signature and footer** tab. The Connection tab's first line names the four, each a link to its
Settings section, and the next part of this guide, on the tools, says what each of those settings
does.

### The Connection tab

Its first line names the four tools that keep their settings on their own screens. Then two
sections, **Airtable** and **Tables**, the tab's **Save settings**, and under it a button of its own,
**Read the lists from Airtable again**.

#### Airtable

| Setting | What it is for |
| --- | --- |
| **Personal Access Token** | Stored in the database and never sent back to the browser: the field shows it masked. Leave it blank to keep the current token. |
| **Schema token** | Optional, and used by the Track Builder alone, to create a track's columns when it is published. Its fold says the rest: it needs the `schema.bases:write` scope and must belong to somebody with the base creator role on the base; left blank it keeps the current one, and typing remove takes it away. Without it, publishing lists the columns for somebody to create by hand. |
| **Token scopes** | The scopes the token needs, listed in the row's fold with what each is for: `data.records:read`, required, for reading mentors, students and tutors; `data.records:write`, required by the Mentor Status Checker, for changing a mentor's status when you promote them (without it the tool can still run in report-only mode, but promoting fails), and for the Mentors row that making a mentor from an application writes, a new row or the Status of the row that already holds the person (without it that mentor is made and invited all the same, and Airtable's refusal waits on the application), and for the check row that `wp wpcredits mentor_airtable_row --check` writes and deletes at the launch; `schema.bases:read`, optional, for listing the bases, tables, columns, statuses and stages the Settings tabs and the Mentor Status Checker's screen offer, and reading each column's description from Airtable (without it each of those settings is typed, and the built-in descriptions are shown instead), and for letting that Mentors row carry the applicant's answers (without it the row holds the name, profile, email and Status alone, and the site never updates it later). Set them on the token itself at airtable.com/create/tokens, with access to the WPCredits base; they cannot be checked from here without writing to the base, so the list is the reference. |
| **Base ID** | The Airtable base holding the program, chosen from the bases the token can open, each with its ID beside its name. Listing the bases needs the token's `schema.bases:read` scope; when the list cannot be read, the ID is typed, and a sentence under the field says why. |

#### Tables

Each table of the base the plugin reads or writes, chosen from the base's tables with its ID beside
its name, and each name column from its own table's columns. When a list cannot be read, the table's
ID or the column's name is typed, and a sentence under the field says why: no Personal Access Token
or no Base ID is set, Airtable refused the token, the token lacks the `schema.bases:read` scope or
access to the base, the base has no such table or column, or the read failed.

Four of these had no control before 1.117.0 and could only be changed in code: the **Tutors**,
**Feedback** and **Countries** tables, and the **Countries** name column.

The bases, statuses and stages are read from Airtable once a day, the tables and columns every
fifteen minutes, and all of them again after a Save of the Connection tab. A read that failed is not
asked again for five minutes, so an Airtable that does not answer holds up one draw of a tab, not
every one, and the fields stay typed meanwhile. **Read the lists from Airtable again**, under the
tab's Save button, reads them at once: for a status or a stage somebody has just added in Airtable,
which cannot be chosen until the lists are read again, or once Airtable answers again.

When its table is chosen from the list, which saves the table's ID, each of the **Institutions** and
**Countries** name columns can also be set to **None (the built-in column)**, which leaves the
setting blank: each is then read from the column the plugin names by default, **Name** for both, and
the Institutions table's by the mentors sync from the table's primary column, which it finds by the
table's ID. A table saved by its name, as one typed before 1.117.0 could be, keeps its name
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
| **Countries table** and **Countries name column** | Read for the name of each institution's country and the Administrator who looks after it; the name column holds each country's name. |

### The Students and mentors tab

Five sections: **Who is on the program**, **When someone leaves**, **Accounts**, **Landing
pages** and **Mentor applications**. The status lists are shared: a current student is anyone a mentor is currently mentoring.

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
  account is created, and each mentor whose account already existed when the sync makes them an
  active mentor**. Off by default, and worth leaving off unless you mean to email everybody: a
  first sync creates around ninety accounts at once. Its fold: invitations are queued and sent a few
  at a time rather than all inside the sync, so a mail limit cannot swallow half of them unnoticed,
  and you can also invite people one at a time from the Mentors and Students screens, or tick
  several in the Students or Mentors screen's list and invite them together. A mentor whose account
  already existed (a returning mentor, or a student or an institution member made a mentor) gets
  the same email, once each time they become active: a mentor who stays active is not sent it
  again by a later run, nor one an application already made a mentor, and never an Administrator.
  A mentor matched to an account by username alone waits until it is sent from the account's row on
  the Mentors screen's **Accounts** tab, since that account may be somebody else's: the run says
  so. When one run would make more than five such mentors active at once, none is sent, since that
  is a base that was changed: each account is held on the same tab, the run's first notice names
  them, and **Send invite** on a row sends the welcome to the ones that should have it. A mentor
  made from an application is sent theirs whatever this says, since it carries the welcome, except
  an Administrator, and a mentor whose account is not yet confirmed as the person's (see
  *Applications to mentor*).
- **Automatic sync** - **Read Airtable on a schedule**, on by default: the students, mentors and
  institutions syncs every three hours, the mentors run half an hour after the students run. Its
  fold: the student rows carry what people are shown on their cards, and the students run is the
  expensive one, reading a WordPress.org profile per mentor, cached for twelve hours; the mentors run
  reads three Airtable tables and no WordPress.org profile. A run already in progress is left to
  finish rather than restarted. The students and mentors runs can also be run by hand from the
  Students and Mentors screens.

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

#### Mentor applications

The form people apply to mentor through, at /become-a-mentor/, since 1.122.31.

- **Applications from mentors** - **Take mentor applications through the form on this site**. Off
  by default, since it is a public page anybody can post to; its address is under the switch. Every
  application is stored for an Administrator to decide on the Mentors screen's **Waiting for
  review** tab, and the form writes nothing to Airtable; while this is off, the page shows one
  sentence saying applications are closed, and an Administrator who opens it a second saying why
  (until 1.123.26 the public were shown nothing here, and on the institution and sponsor forms
  too, whatever their rows said). As with the other two
  forms, its consent box links to the WordPress.org privacy policy, not to this site's own privacy
  page (since 1.123.24), and the row's fold says so.
- **Make mentors from applications** (since 1.122.33) - **Make a mentor of each selected applicant
  whose course shows, or for whom The course is completed is pressed**. Off until the launch, since
  on means accounts made, invitations sent and rows written in the shared Airtable base, which
  somebody decides on the day rather than an update deciding it. Its fold: while it is off, an
  applicant whose course shows waits under Selected (course pending). What it does, and what the
  launch asks of a person beforehand, is under *Applications to mentor* in the part on the plugin
  in wp-admin.
- **Who is mailed** - the addresses told of each new mentor application, chosen as the
  Institutions tab's reviewers are; with none here, every Administrator is written to. The email
  names the applicant and their WordPress.org profile and links the Waiting for review tab; it
  carries none of their other answers. Point it at your own address before you try the form, or
  every Administrator hears of the test.
- **Mentors' course invitation link** - the link the selection email gives a selected applicant to
  join the mentors' course. Its fold: Select is refused while it is empty, since the selection
  email would have no link to give, and only an https link is kept.
- **Selected applications lapse after (days)** - from 14 to 365, 90 by default: a selected
  applicant whose course has not shown after this many days is moved to **Lapsed** with a dated
  note, and Select is offered on the row again.

### The Institutions tab

Five sections, headed on the screen **Applications and enrollment**, **Landing page**, **When an
institution leaves the pipeline**, **Collaboration Agreements** and **Semester reports**.

#### Institution applications and enrollment

- **Applications from institutions** - **Take applications through the form on this site**. Off by
  default, since the form is a public page anybody can post to; its address is under the switch.
  Since 1.123.27 the form is at /institution-application/, titled "Bring WordPress Credits to your
  institution", and /apply/, its address before, takes a visitor there.
  Every submission is stored for an Administrator to read on the Institutions screen's Waiting for
  review tab and to decide on the Administrator Dashboard, and nothing is created until somebody
  approves it; while this is off, the page shows one sentence saying applications are closed. Since
  1.123.24 the form's consent box links to the WordPress.org privacy policy, not to the page chosen
  under Settings > Privacy, so the form no longer waits on this site having a privacy page, and the
  row's fold says which policy it links. Since 1.123.29 the form also asks the contact's role at the
  institution and, from the mentor form's list, the languages the students study in, both required,
  and under **The agreement**, who will sign it for the institution, their title and the
  institution's page about WordPress Credits, none of them required, though a title is asked for
  when somebody is named to sign and a name when a title is given. These five answers are kept on
  this site and never sent to Airtable. On approval they are copied to a record of the institution
  on this site, kept under its Airtable record ID, so they are still there once the application
  has been deleted. When the approval joins a record the program already had, they fill only what
  that record does not hold yet, and a second press of **Approve** changes nothing. If the site
  cannot keep them, the approval stops before the application is called approved, the Airtable
  record and the account already made stay, and pressing **Approve** again finishes it. An
  institution approved before 1.123.29 has no such record.
- **Enrollment lists from institutions** - **Let an institution send a list of students to
  enroll**. Adds an "Enroll students" section to the Institution Dashboard, where a school chooses
  the program and the term and then adds one student or sends a CSV. Off by default. Beside the file
  box, a link **Download a sample list** sends a file, `students-sample.csv`, with the four headers
  the import reads (`name`, `email`, `field_of_study`, `tutor`) and one example student, so a school
  that has never seen the format is shown one. Since 1.123.41 the import refuses an address at
  example.test, the example's domain, as the sample list's own row ("This is the sample list's
  example student. Replace it with one of your students, or delete the row."), so a sample sent
  back unchanged creates nobody; the other rows of a file are checked as usual. The site makes the file
  when the link is followed, to an account that may enroll students, and holds no copy of it. Its
  fold: the list is read and checked against the program records, and the school sees what was
  understood before anything is created; while this is off, the section does not appear at all. Each
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

- **Agreement review** - how many days, from 1 to 60 and 3 by default, a signed agreement or an
  institution's application may wait before the queue marks it overdue, and an agreement before
  the nightly reminder names it.
- **Who is emailed about institutions** (called **Who reviews agreements** until 1.123.26, the
  same setting) - who gets every email about institutions an Administrator is sent: a new
  application from an institution, a signed agreement uploaded for review, the nightly reminder of
  agreements waiting past the review days, the notice that the agreement template cannot be used,
  an institution's last member leaving, and the three emails about a school's details: a school
  saving a correction of its contact or agreement details (what changed, from what to what), a school
  asking to correct its name, website, city, country or contact's email (what it asks for, with a link
  to the request on the Administrator Dashboard), and a school withdrawing that request. A box for
  each Administrator, under **Administrators**, and an **Other addresses** line for anybody else,
  separated by commas. With nobody named, every Administrator account gets them, and a notice under
  the field says so: "Nobody is named, so every Administrator account gets these emails." That
  means every account with the WordPress Administrator role, technical ones too, so name somebody.
  Where no Administrator account with an email address can be listed, the addresses are typed,
  separated by commas.
- **The agreement wording** - the Google Doc the plugin's copy of the Collaboration Agreement was
  taken from, linked from the Agreement template card on the Institutions screen's Agreements tab;
  Google addresses only. Its fold: it is kept on this site rather than in the code, because the
  document is editable by anyone holding its link and the plugin's source is public.

#### Semester report drafting

- **Drafting** - **Draft each institution's semester report when the semester ends**, on by
  default: a daily job drafts a report for every finished semester and tells the Administrators.
  Off, drafts are written only when an Administrator presses Draft now.
- **Drafting grace** - how many days after a semester ends, from 7 to 365 and 45 by default, the job
  waits for students still in progress before drafting anyway. The draft says how many were still
  in progress.
- **Who reviews reports** - who is told when the job drafts a report, chosen as the agreements'
  reviewers are. With none, every Administrator is written to.

### The Sponsors tab

Six sections, headed on the screen **Applications**, **Landing page**, **When a sponsor is no longer
Approved**, **Interest mail**, **Offers** and **Tiers**.

#### Sponsor applications

- **Applications from sponsors** - **Take sponsor applications through the form on this site**, at
  /sponsor-application/. Off by default, since it is a public page anybody can post to; its address
  is under the switch. Every submission is stored for an Administrator to read on the Sponsors
  screen's Waiting for review tab and to decide on the Administrator Dashboard, and nothing becomes
  a sponsor until somebody approves it; while this is off, the page shows one sentence saying
  applications are closed. As with institutions, its consent box links to the WordPress.org privacy
  policy, and the row's fold says so.

#### Sponsor landing page

- **Sponsor landing page** - **Send sponsor accounts to the Sponsor Dashboard when they log in**,
  instead of the wp-admin Dashboard, on by default. Accounts that can also edit content or manage
  the program are left where WordPress sends them. The page's address is under the switch.

#### When a sponsor is no longer Approved

- **Its accounts** - **Remove its accounts from the sponsor, so they lose access to its Sponsor
  Dashboard**, or **Leave their access in place**, the default. Nothing is ever deleted, and Paused
  and Not Moving Forward sponsors keep their accounts by default, because a pause is often short.
  Its fold: the status is the record, and the first answer has a status change on the Sponsors
  screen remove the accounts of a sponsor that is no longer Approved.

#### Interest mail

- **Who is mailed** - the addresses mailed in place of a sponsor's Administrator when it has
  none, chosen as the Institutions tab's reviewers are; with none here, every Administrator is
  written to. Every mail meant for a sponsor's Administrator goes to its assigned Administrator:
  its interests, a mentor it would like to sponsor, its signed agreement, a problem reported with one
  of its codes, a warning that its codes are running low, the mail thirty days before its term ends
  and the mails about the events it plans, changes or cancels. A sponsor with none is mailed here,
  and every new sponsor application comes here too, since a new application has no Administrator yet.

#### Offers

- **Largest logo** - the largest logo file, in KB, from 100 to 8192 and 1024 by default, for the
  logos sponsors upload and the ones sent with an application. PNG, JPEG and WebP only; never SVG.
- **Tools from our sponsors** - two boxes: **Show the section on the Student Report Card**, on by
  default, and **Show the section on the Mentor Report Card**, off by default. The section lists the
  live offers, each with its claim button. Its fold: a student always sees offers open to students,
  a mentor sees the offers whose sponsor opened them to mentors, and the Administrator Dashboard
  shows every live offer whatever these say.
- **Low-stock warning** - when a pool of one-time codes falls below this many codes, from 1 to 1000
  and 10 by default, the sponsor and its Administrator are mailed, once per crossing. Its fold:
  this is the default for new offers, and each offer can set its own.

#### Sponsor tiers

- **Sponsor tiers** - **Launch the sponsor tiers**, the launch switch for the tiers, off by default:
  ticking it is the launch. Turning it on publishes the Partners page; turning it off returns the
  page to draft. Its fold lists everything else it turns on. While it is off, the switch gates the
  Partners page and the Sponsors block (the full list and the strip on the landing page alike), the
  badge a sponsor puts on its own site, the Handbook listing tab on the Sponsors screen, the
  application form's *Which level are you considering?* question, the partnership tiles a track's
  students see on their Student Report Card and the offers its partners keep for them, the At a
  glance tiles for the tier and for a returned event, the *Sponsorship*, *Badge*, *Reports* and
  *Events* cards, the badge in the Sponsor Dashboard's header, the reports a sponsor's members
  open on the *Reports* card and the event planner on the *Events* card, the Administrator
  Dashboard's *Events* card and its tile, which the Overview lists too, the *Events* card on the
  Sponsors screen's Waiting for review tab and the events in its menu bubble, and the *Events from
  our sponsors* lists, the reminders the day before each approved event, the description for the
  partner page, the caps a tier puts on posts, and the dormant state; the daily run puts nothing to
  dormant and wakes nothing. A signed-in Administrator sees the Partners page and the Sponsors block
  while the switch is off, under a note that visitors do not see them yet; the badge and the
  application's tier question stay refused to everyone, Administrators included, and the Handbook
  listing tab stays out of the Sponsors screen's tab bar, its address opening the first tab, until
  the switch is on. Administrators can set tiers, terms and funded lines on the Sponsors screen
  whether it is on or off, the Sponsors screen and the Administrator Dashboard show them either way,
  and the mail that warns of a term's end thirty days ahead goes out either way. Switched off again
  after being on, it leaves whatever is dormant where it is until it is back on, and the sponsor
  reads only that the program is holding it back.
- **Tier grace date** - 31 December 2026 by default. On or before this day nothing a sponsor's tier
  does not cover goes dormant, even with the switch on. Its fold: from the day after, the daily run
  applies the tiers as set. Until the day has passed, the Sponsors screen says what the run would put
  to dormant on the day after it. A value that is not a real date leaves the stored day in place.

### The Security tab

Two sections, **Two-factor authentication** and **Claude access (MCP)**.

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

#### Claude access (MCP)

Lets the Administrators ticked here ask Claude questions that the site answers from its own
records, read-only, through the MCP Adapter plugin. The section is saved with the tab's one
**Save settings**, along with the roles above it.

- **Turn it on** - **Let Claude read the program's figures**. Off by default. Under the box, one
  sentence says whether the MCP Adapter plugin is active: when it is not, nothing can connect
  whatever the box says; when it is active but older than 0.7.1, the version Claude access was
  written against, the sentence names the adapter's version and says Claude access needs 0.7.1 or
  later, so nothing can connect; when it is active at 0.7.1 or later, the sentence names the
  adapter's version and the address Claude connects at, the site's /wp-json/mcp/wpcredits. Every
  field of the section is written on every Save, so change this switch one way at a time: a tab
  opened before a change made from the command line, and saved afterward, writes the old value back.
- **Who may connect** - a box for each account that holds the program's management capability,
  named "display name (login)", or by the login alone when the account has no display name or its
  display name is its login, ticked for the accounts that may connect. Nobody by default. An
  account that is no longer drawn, because it was deleted or is no longer an Administrator, is
  taken off the list at the next Save; unticking every box empties the list. Under the boxes: each
  person creates their own application password on their profile, with a name that begins with
  Claude, for example "Claude - laptop". Its fold: a password with any other name does not open this
  address, and a password named this way opens nothing else.
- **What the address checks** - every request to the address is checked before anything answers, in
  this order, and the first thing that fails is the answer. No account: refused with 401, and nothing
  is logged, so a stranger cannot fill the log. The switch off: the address answers 404 and the check
  asks again all the same. A sign-in that is not an application password named Claude, such as a
  browser session or a password with any other name: refused with 403. An account that does not hold
  the program's management capability, or is not ticked under **Who may connect**: refused with 403.
  Each refusal of a signed-in account is a row in the log, one for each request however many times
  WordPress asks about it, and 60 an hour at most for each account; a question refused for being
  over the hour's, the day's or the lookups' limit counts in the same 60. The other half is the fence: a password named Claude opens this address and nothing else. Used on any
  other REST route or on the batch route it is refused with 403, and anywhere else a sign-in with it
  fails: on XML-RPC, and on any request a plugin opens to application passwords, such as the
  VideoPress requests Jetpack opens in wp-admin. That holds whether the switch is on or off, and on
  a REST route the refusal shows in the log as "Refused: another address". A password counts as named Claude when its name begins with Claude, in
  any case and with spaces before it: "Claude - laptop" does and "Claudette" does not.
  Before deactivating this plugin or installing a version below 1.123.30, revoke every application password whose name begins with Claude, or check that there is none: without the plugin, such a password opens every address its account can use.
- **Limits** - three numbers, each counted per person. **Answers an hour, per person** is 120 by
  default and from 1 to 1,000, and **Answers a day, per person** is 600 by default and from 1 to
  10,000; both count across every answer. **Lookups of people an hour, per person** is 40 by
  default and from 1 to 200, with a line under its field saying so, and counts only the questions that
  look people up (A student's tracks, A mentor's students and Mentor applications), on top of the
  other two: a lookup of people also takes a place of the hour's answers and of the day's. When it is the
  lookups' limit that is reached, the question is refused with a message that names the limit, for
  example "The limit of 40 lookups of people an hour is reached.", and the time the hour starts
  again, and the refusal is logged as "refused: people_limit". That question takes no place of any
  of the three, and every other question goes on answering. A number outside its range is saved as
  the nearest end of it.
- **Keep the log for** - a number of days, 90 by default and from 7 to 365, outside which it is
  saved as the nearest end. Every answer and every refusal of a signed-in account is logged and kept
  for this many days.
- **Latest calls** - under the rows, a heading and a table of the newest 100 calls in the log, which
  the tab only reads and the Save does not touch. A row has **When** (in the site's date and time
  formats), **Who** (the account's display name, or "A removed account" when it has been deleted),
  **Password** (the name of the application password the call was signed with, never the password),
  **Asked**, **Outcome**, **Rows** (how many of the program's records the answer named) and **Time
  taken** in milliseconds. **Asked** is the question's name, for example "Program counts"; "Refused
  at the door" for a call that was turned away before any question was named, such as a browser
  session or a password not named Claude; and "Refused: another address" for a Claude password used
  anywhere but the MCP address. **Outcome** is answered, failed, or refused with its ground, for
  example "refused: hour_limit". Nothing asked yet shows "Nothing has been asked yet." instead of the
  table. A failed answer, and a server the MCP Adapter could not create, also leave one line in PHP's
  error log, at most one an hour of each, and nothing else: for an answer, the question's name and the
  exception's class, `not_an_answer` or `invalid_output`; for the server, the error's code or the
  exception's class. The table scrolls inside its own box, which a keyboard reaches. Under it: "See more with
  wp wpcredits mcp_log." That command lists up to 1,000 calls, newest first, as a table, JSON or CSV
  (`--limit` from 1 to 1,000, 100 by default; `--format`), with the columns when, account (the login),
  password, ability, outcome, reason, rows and ms; JSON and CSV also give input (what was asked, as
  JSON) and records (the IDs of the records that went out) after reason. A daily job deletes the
  calls older than **Keep the log for**, in batches of 500, the oldest first, up to 10,000 rows a run and the rest the next
  day, never fewer than 7 days whatever the setting holds, and no other row of the audit trail; it
  runs whether the switch is on or off.
- **What Claude can ask** - seven questions, answered from the records the site already keeps and
  never from Airtable or WordPress.org. Four give figures and name no person, and each says when its
  sources were last read (for What waits for an Administrator, when each sync last ran; for Mentor
  engagement, the moment it counts as of). **Program counts** gives the figures the Administrator
  Dashboard counts for the current semester: students per track, mentors, institutions per stage,
  sponsors per tier, mentor applications and the outcome figures. **What waits for an Administrator**
  lists the tiles of the dashboard's attention strip, with how many wait and where to open each, and
  when each sync last ran and runs next and whether the Syncs and health card counts it among those
  that need a look. **Mentor engagement** says how many active mentors signed in, were seen, acted
  or stayed idle, as the Engagement tab counts them, as of a day. **One institution** is found by
  its record ID or by part of its name, five at most at a time, and gives its pipeline stage, country
  and city, its Collaboration Agreement's status and dates, how many accounts act for it and its
  students per group, for one semester or for all, and when the institutions and, separately, the
  roster behind the students were last read. An institution's contact, the person who
  accepted its agreement and its members are never named, and every name and label in an answer is
  cut to one line of at most 120 characters. The other three look people up, in the next point.
- **The three that look people up** - **A student's tracks**, **A mentor's students** and **Mentor
  applications** are the **lookups of people** that the third limit above counts, and the only
  questions that do. **A student's tracks** finds a student by an email address, a WordPress.org
  username or profile address, a Students record ID or three characters or more of a name, for one
  institution or for all, and lists ten people at most; for each person, whether they have an account
  on this site and every track they hold, one for each Students row (a row marked Duplicated or SPAM
  is never one), with the track's Students record ID, its status, institution, cohort, start and end
  days, contribution team, hours, how many reports it has, whether a mentor is assigned and that
  mentor's Mentors record ID, and the course the student took while they wait to graduate. A student
  with no account on this site has no row on the **Student accounts** list, and an Administrator puts
  the name to the ID on an institution's roster instead (see *Looking a person up*). **A mentor's
  students** finds a mentor by a Mentors record ID, a WordPress username, an email address or a
  WordPress.org profile address, and only a mentor: an account that is not on the Engagement tab is
  nobody. In both questions, a username is read as one only when it is typed whole, so a name with a
  letter a username cannot hold finds nobody by username; here a Mentors record ID is also matched
  letter for letter in its case. It gives the mentor's Mentors record ID, whether the mentor is active, the days of
  their last sign-in and last visit and the Engagement view they are filed under, and their students
  as the Mentor Report Card lists them, the current ones before the past ones and 25 at most, each by
  the Students record ID of the track and with the status, the hours the card prints and the start
  day. **Mentor applications** lists the applications in one state, the
  newest received first and 25 at most (`open`, the default, which is the queue on the Mentors
  screen's **Waiting for review** tab: new, held, asked for more information or waiting for a second
  opinion; `selected`, waiting for the course; or `lapsed`), each by its reference, with the day it
  was received, its state, the days it has been in that state, the country, the languages, the hours a
  week and the contribution areas the applicant chose, the teacher answer (yes or no), whether the
  address is confirmed and the **possible duplicate** and **held** marks; with it, how many
  applications are open, selected and lapsed. The country, the languages and the areas are given only
  when they are on the application form's own lists: an answer edited by hand to anything else is
  left out (the country comes back as none), and so are all of them while the site has not synced the
  list of countries or of contribution teams. One filed as spam, declined or made a mentor is never
  listed.
- **People come back as record IDs, and never by name** - a student is the Students record ID of each
  track they hold, a mentor is their Mentors record ID, and an applicant is their application's
  reference, such as MAPP-2026-0007. No answer holds a name, a display name, a login, an email
  address, a WordPress.org username or profile address, a Slack handle, a tutor's or a mentor's name,
  a website, or any text a person wrote: not an applicant's free-text answer, not a note, not the
  accessibility or survey answers, not what the form's checks noticed (but the possible duplicate
  mark) and not the consent evidence. What you type to find someone, a name or an address, is kept in
  the log beside the question, cleaned to one line of at most 120 characters, and is never repeated in
  an answer. Claude cannot say whose a record ID is: an Administrator puts the name to it on the site.
- **Looking a person up** - search the ID or the reference where the person is listed. A student's
  Students record ID goes in **Search students** on the **Student accounts** list of the Students
  screen, which finds the account of every track that holds it, so a student with an account is found
  there. Any student, with an account or not, is found by the same ID in **Search** on the roster of
  the institution that the track names in the answer, on the Institution Dashboard opened through the
  switcher, in the cohort the track started in: an Administrator's search there reads the Students
  record ID as well as the name, exactly as the ID is written, and the box's hint lists it. A track
  that names no institution (its institution comes back null) is on no roster. Its student's account,
  if there is one, is on the **Student accounts** list, and the row is listed by its name and status,
  without its ID, under **Rows with no institution** on the **Sync and storage** tab of the
  Institutions screen. A mentor's Mentors record ID goes in **Search mentors** on the **Mentor
  accounts** list or on the **Engagement** tab of the Mentors screen; a mentor made by hand, whose
  account holds no Mentors record ID, comes back with none and is found by username or email address.
  An application's reference goes in **Find by reference**, above the views of the Mentors screen's
  **Waiting for review** tab: it draws the application whole, with its decisions, whatever its age. The
  reference is also printed over the applicant's answers in every view of that tab, but a view draws
  50 applications at most: the oldest 50 in Waiting, Asked for more information, Second opinion,
  Selected (course pending) and Lapsed; in Activated, those still waiting for their Airtable row
  first, then the most recently made mentors; and, under every view, in Recently decided, the 50
  most recently declined or filed as spam, newest first. **Find by reference** reaches the ones past
  them. A reference is the one the applicant saw on the thank-you page.

### The Mail tab

Two sections, headed on the screen **Invitations** and **Recent mail**. No settings and no Save:
each points to the **Emails** tool, where the emails are edited and their record is kept.

#### Invitations waiting to be sent

How many invitations are still waiting to be sent, when any are; they go out a few at a time in the
background. What each invitation says, and a test of it sent to your own address, are in
**WPCredits Program > Tools > Emails**, on its **Templates** tab, which **Open the email templates**
opens. Until 1.122.19 this section had a button for each of the four invitations that emailed you a
sample; **Send me a test** on each template's editor does that now, with the invitation as it is
saved.

#### Recent mail

This section points to **WPCredits Program > Tools > Emails**, which keeps every email the site
sends for 30 days, with **Open the email log**. When an email failed in the last 30 days, the
section says how many and links to them.

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

How long a decided application is kept, institution, sponsor and mentor applications alike, and the
one proxy the three application forms believe.

- **Spam kept for (days)** - from 1 to 365, 30 by default: an application marked as spam is deleted
  this many days after it was marked.
- **Rejected kept for (days)** - from 30 to 3650, a year by default: a rejected or declined
  application, or a mentor application that lapsed, is deleted this many days after the decision,
  and one the checks held, which has no decision, this many days after the last thing that happened
  to it. Either way the wait is long enough to recognize the same applicant applying again.
- **Approved kept for (days)** - from 0 to 3650, 0 by default, which keeps approved applications,
  and the mentor applications of those who became mentors, forever: they are the record of who was
  let in. An application made a mentor whose Airtable row is still to be written is kept whatever
  this says, since Write the Airtable row again needs its answers.
- **Trusted proxy** - the one IP address, not a range, of the proxy whose forwarded header the
  application forms believe for a sender's address, which their limits count by. Its fold: leave it
  empty unless the site sits behind a proxy, since anybody can send that header; anything but one
  address, a range included, is saved as empty.

The daily run that keeps these days for the mentor applications also lapses a selected application
whose course has not shown after the days set under **Mentor applications** on the Students and
mentors tab, and what every deleted mentor application counted for the public feed of application
figures is kept, so the feed's months do not change as rows go.

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
  join an institution's account stays listed once it was accepted, canceled or ran out, so an
  Administrator can still see who was invited and never came.
