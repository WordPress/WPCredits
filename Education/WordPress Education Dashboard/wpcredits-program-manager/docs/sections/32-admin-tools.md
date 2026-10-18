## Tools you run on their own

Three of the tools keep settings of their own: the **Mentor Status Checker**, the
**Student Duplicate Finder** and **Need help?**. Each shows them in a **Settings** section at the top
of its screen, a form with its own **Save settings** that saves that tool's settings and nothing
else, and the notice after a save, on the same screen, names the tool. The Settings screen's
Connection tab names the three, each a link to its section. On the **Tools** screen, a
tool that cannot run says why on its card, in its own words: the Airtable connection for the Mentor
Status Checker and the Student Duplicate Finder, and for Need help? its switch or its provider.

### Header notices

**WPCredits Program > Tools > Header notices.** One notice per audience - Students, Mentors,
Institutions, Sponsors, Administrators - each in its own editor on one screen, with a single Save
button underneath.

{{image:admin-header-notices|One editor per audience. An empty notice is not shown; there is no separate switch.}}

- **An empty notice is off.** There is no separate switch, because a switch is one more thing to leave
  in the wrong position.
- **Anyone in two audiences sees both notices.** An administrator who also mentors gets the mentor
  notice and the administrator one, mentor first. Audience membership uses the same tests the
  dashboards do, so an administrator matched to an Airtable mentor record counts as a mentor even
  though the sync never gives them the role.
- A notice appears at the top of the content, which in this program's theme is the top of the
  dashboard card - not above the site header.
- Links and simple emphasis survive; scripts and other markup are stripped **on save**, so nothing
  dangerous is stored rather than merely hidden at render time.

### Emails

**WPCredits Program > Tools > Emails.** The plugin's emails you can edit, by area. One you have not
edited is sent as the plugin ships it. Every email the plugin sends ends with the signature and
footer. The tool also keeps a record of every email the site sends, for 30 days. Three tabs,
**Templates**, **Signature and footer** and **Log**; the tool's own address opens Templates.

#### Templates

The plugin's emails, grouped by area, each area in a table of its own. There are six:

- **Invitations**: the student's, the mentor's, which since 1.122.33 carries the welcome a new mentor
  is sent, and the sponsor's invitation, and the institution's two, one for an institution whose
  Collaboration Agreement is not on file yet and one for an institution whose agreement is on file.
- **Mentor calls**: what the mentor and the students are told about one-to-one calls, group sessions
  and series of sessions: a booking, a join, a series joined, a session moved, a place left or
  released, a call or a session canceled, and the reminder a day before. Where the mentor and the
  student are told different words, each has an email of its own, named for whom it goes to; a
  group session's cancellation and its reminder say the same words to the mentor and to every
  student on it, so each is one email. A mentor who has not set a meeting link yet is told so where
  the others are told where to meet, so the mentor's three confirmations, of a call, of a join and of
  a series, each have a second email for that case.
- **Institutions**: what an institution and the Administrators are told from its application to
  its last member: the acknowledgement of an application, the notice of it to the Administrators, an
  Administrator's question and the rejection; a member's invitation to a colleague; the institution's
  Collaboration Agreement received, uploaded, accepted, put back in force, returned with a note and
  revoked, the daily list of agreements waiting too long, and the notice that the agreement's
  template could not be used; and the notice that an institution has no members left. An email that
  says other words in one case than in another is two: the acknowledgement for a country with a call
  to book and for one without, the upload with the courtesy scan's notes and without, the
  revocation with a country contact named and without one, and the invitation with its inviter
  named and with nobody to name as its sender, the inviter's account gone or holding no name.
- **Semester reports**: the notice to the Administrators that the daily job drafted a report, the
  approval to the institution's accounts, and the question to the students whether the report may
  mention them.
- **Sponsors**: the acknowledgement of a sponsor application (and its twin for an application whose
  logo files were not kept), the notice of it to the Administrators, an Administrator's question and
  the rejection; the sponsor's Collaboration Agreement received, accepted, returned with a note, out
  of force and back in force; what a sponsor says on the Sponsor Dashboard it would like to support,
  and its request to sponsor a mentor; the warning that an offer's codes are running low and a
  claimant's report of a code that did not work; and a sponsor's post sent back to its author.
- **Mentor applications** (since 1.122.31): what a person who applied to mentor through the form on
  the site is told, the acknowledgement with the link that confirms the address, the selection with
  the mentors' course invitation link, the request for more information and the decline, and the
  notice of a new application to the Administrators. The four to the applicant ship with the words
  the program's Airtable automations sent, and carry the program's contact address as their
  Reply-To when **Contact address in emails** is set, so an answer reaches the program.

Where an email is two, as the mentor's confirmations with and without a meeting link and the five
above are, each of the two is edited on its own: an edit of one leaves the other as shipped. So an
Administrator's edit of "Invitation to join an institution" does not carry over to "Invitation to
join an institution, with no sender named": that invitation is sent as shipped until it is edited
too.

Each row names the email, who receives it and what sends it, its state, and who edited it last and
when. The states:

- **Shipped text**: the email is sent as the plugin ships it, which is word for word what it said
  before it could be edited: before 1.122.19 for the Invitations, before 1.122.21 for the Mentor
  calls and the Semester reports, and before 1.122.22 for the Institutions and the Sponsors, but
  for what 1.122.23 changed on purpose: the warning that an offer's codes are running low says
  "1 code left" for one, writes a count past a thousand and the mark beside it alike, and leaves
  out its Offers card line while the Sponsor Dashboard's page is not published; an institution's
  Collaboration Agreement sent back or revoked by an Administrator whose account holds no name, or
  only spaces, says "by an Administrator", and a sponsor's sent back by one says
  "An Administrator"; the invitation with nobody to name as its sender is an email of its own; an
  invitation to an institution says that a reply reaches the person who sent it only while it
  carries their address to reply to; the two emails about what a sponsor would like to support
  name the site; the notice of an upload the courtesy scan noticed something in says what in
  words; and every email prints a name as it was typed, its subject included, as below.
- **Edited**: it is sent as an Administrator wrote it, to everybody, in that wording.
- **Edited, and the shipped text has changed since**: an update changed the shipped text after the
  edit was saved. The edit is still the one sent, and its editor opens the new shipped text under
  the buttons, to compare. Saving your edit again clears this state.
- **Edit set aside**: the edit uses a placeholder the email no longer has, lacks one it now needs, or
  has one in its subject that goes in the body, so the shipped text is sent until the edit is saved
  again; the state says which placeholder.

**Edit** opens the email's editor: a **Subject** and a **Body**, the email's placeholders, each with
what it means and an example, and a **Preview** of the email filled with the examples and ending
with the signature and footer. A placeholder, such as `{set_password_link}`, is a word in braces that
is replaced by its value as the email is sent, as a person reads it: a name or a title WordPress
stores with an "&" or a "<" written as an entity, an account's name or an application's among them,
arrives as it was typed, in the body and in the subject. The subject of every email in this list is
plain text since 1.122.23, as an edited one's has been since 1.122.22, so a "<" or an "&" in it is a
character, never an entity, and nothing in it that looks like a tag is taken out; a line break in it
is one space.
A sentence whose placeholders are all empty is left out, and so is a line left with nothing on it,
together with the line ending in a colon directly
above it when that line holds no placeholder: an invitation with no dashboard page published leaves
out the line that would name it, as it always did. A sentence ends at a full stop followed by a
space; a question or exclamation mark does not end one. So in "Thanks! Write to {program_contact}."
with no contact address set, the whole of it goes, "Thanks!" with it, and in "e.g. " the full stop
ends a sentence.

An email about a call shows every time on its recipient's own clock: `{when}` is the call's start
and end as the recipient reads them, and `{time_zone}` names that clock, so a mentor in Tokyo and a
student in Warsaw each read the call at their own hour. A date in an Institutions or a Sponsors
email, such as `{accepted_on}`, is the site's, as it always was. A count arrives in words, such as
`{session_count}`, "3 sessions", or `{review_window}`, "3 working days", so a template needs no rule
for one and several. So does the number of codes left in the warning that an offer's codes are
running low, `{codes_left}`, "3 codes", since 1.122.23, where it arrived as a number: an edit of that
email saved before, which says "{codes_left} codes left", reads "3 codes codes left" until it is
edited again. Past a thousand, the count and the mark it warns below, `{low_mark}`, are written
alike, "4,999 codes" and "5,000". What the courtesy scan noticed, `{scan_flags}`, is in words since
1.122.23, each with the PDF's own name for it in brackets, such as "an action that runs when the
file is opened (/OpenAction)", in an edit saved before as well; the Institutions screen, the
Sponsors screen and the Administrator Dashboard name what the scan noticed in the same words. Both
invitations to an institution, the one with its sender named and the one with nobody to name, gain
the optional `{replies_reach}` in 1.122.23, so an edit saved before, which lacks it, is still sent
as edited. An invitation to an institution, with its sender named or not, says that a reply reaches
the person who sent it, `{replies_reach}`, only while it carries their address to reply to; empty,
its sentence is left out. The two emails about what a sponsor would like to support gain an
optional `{site_name}`, which names the site in their subject, so an edit saved before, which lacks
it, is still sent as edited. An edit of an email whose shipped body ends with a line break, as a
Semester reports email's does, ends with one too, after its signature and footer.

- **Save** stores the subject and the body, and is refused in the cases below.
- **Send me a test**, beside **Save**, sends the email as it is saved, filled with the example values
  and with "[Test]" before its subject, to your own address and to nobody else. The Log marks it
  **Test**. Save what you typed first: pressed with typing not saved yet, it sends nothing, and the
  editor comes back with your typing and says so. A test carries the subject and the body alone: the
  calendar file a Mentor calls email carries, and the reply address that several emails go out with
  (a Mentor calls email, an Administrator's question to an applicant, an agreement sent back with a
  note, a sponsor's agreement taken out of force, a colleague's invitation, a code that did not
  work), are added when it is really sent, so a test of one arrives without them.
- **Restore the original** is there once the email is edited. It asks first, then drops the edit,
  and the shipped text is sent from then on.

**Save** is refused, nothing is stored, and the editor comes back with what you typed, when:

- a box is empty;
- a required placeholder is missing. Every invitation needs `{set_password_link}`, the link that
  sets the password, and so does every other link that carries a secret: an institution
  application's confirmation link, `{verify_link}`, and a colleague's invitation to an institution,
  `{accept_link}`. Every email about a call needs `{when}`, its time (a series, `{session_dates}`),
  and a semester report `{semester}`. What an Administrator or a sponsor wrote, where it is the
  email's point, is needed too: a question to an applicant, `{question}`; a note on a returned or
  revoked agreement or a returned post, `{note}`; and what a sponsor said, `{interest}`. A returned
  post needs its title and its author's name, `{post_title}` and `{author_name}`, either of which
  can be empty. And an email sent only with a value its other words lack needs it: the mentor's
  confirmation sent with a meeting link, `{meeting_place}`; the acknowledgement with a call to book,
  `{booking_link}`; the upload with the scan's notes, `{scan_flags}`; the revocation with a country
  contact, `{country_contact}`;
- a link or a typed text that goes in the body is in the subject: a link that carries a secret, a
  mentor's meeting link, `{meeting_place}`, which can carry the meeting's passcode, a code's last
  characters, `{code_ending}`, what a person typed, such as `{topic}`, `{question}` or `{note}`, or
  an applicant's own answers (an address, a website, a contact, a city). A subject is not a link
  anybody can follow, and the Log keeps it;
- a placeholder is not one of the email's (a misspelled one is named as one the email does not
  have; a misspelled required one is reported as missing instead);
- WordPress would remove part of the text; or
- the subject is longer than 200 characters or the body longer than 10,000.

A save of the shipped text as it stands stores nothing, so the email stays shipped text. A subject
made of placeholders alone, such as `{mentor_name}`, is saved; if they all come out empty, the
email goes out with its shipped subject rather than with none, since an email with no subject is
not sent.

#### Signature and footer

The one block every email the plugin sends ends with, after a blank line and a line holding `-- `.
It is not added to the emails WordPress and the Two Factor plugin send themselves. Its placeholders
are `{site_name}`; `{dashboard_link}`, the recipient's dashboard, which is empty for somebody with
no account, such as an applicant, and for an email that names the dashboard already, as every
invitation does, so its line is left out and no email names the address twice; and
`{program_contact}`, the address in **Contact address in emails**, which is empty until it is set,
so its sentence is left out. It ships as three lines:

- `WordPress Credits, WordPress Education`
- `{dashboard_link}`
- `This email comes from the WordPress Credits program. Questions? Write to {program_contact}.`

The tab edits it with the editor every template has, without a subject, with **Save**, **Send me a
test** (a short email that ends with the block) and **Restore the original**. An empty block is not
saved, since every email ends with it.

#### Settings

At the top of the Signature and footer tab, the tool's one setting, **Contact address in emails**:
a mailbox the Administrators read, which the footer names for questions. Left empty, the footer
leaves that sentence out. An address that is not one is not saved, and the tab says so.

#### Log

A record of every email the site sends, kept for 30 days: the plugin's own, WordPress's (a password
reset, for example) and the Two Factor plugin's sign-in codes. Use it to answer "did they get the
email?".

Each row is one recipient of one email: when it was sent, in the site's time zone; the address as
plain text, and under it the person's name when the address belonged to an account at the time,
which is a link to the account for as long as the account exists; the recipient type (Student,
Mentor, Institution member, Sponsor member, Administrator, Applicant (no account) or Other); the
area that sent it (Invitations, Mentor calls, Institutions, Semester reports, Sponsors, Mentor
applications, WordPress, Two Factor or Other); which email it was; its subject; and its status. The
status is one of three:

- **Handed to the mail server**: the site passed the email on. This does not prove it reached the
  inbox: a full mailbox or a spam filter can still stop it after it leaves the site.
- **Failed**: WordPress could not hand it over; the reason it gave is shown under the status.
- **Not confirmed**: another plugin took the email over before WordPress could say what happened,
  or the request ended, with a fatal error for example, before it did.

A **Test** mark beside the status shows a test you sent yourself with **Send me a test** (or, before
1.122.19, a sample from Settings > Mail). It is not a fourth status: the test has one of the three
as well, and the **Status** filter has a **Test** choice that lists the tests.

To find an email, type part of an address, a name or a subject into the search box, or narrow the
list by **Area**, **Recipient type**, **Status**, **Email** or **When** (today, the last 7 days, the
last 30 days, or from and to dates), and press **Filter**. The **Email** filter is one field: type
a few letters of the email's name and pick it from the list. Fifty rows show a page, newest first,
with the count above them. Until 1.122.21 the Mentor calls emails were named by five names that each
covered the mentor's copy and the students' alike, such as **Call booked** and **Call reminder**;
the rows sent before keep those names, which now end "(until 1.122.21)", for the 30 days the log
keeps them. Until 1.122.22 a Collaboration Agreement put back in force was logged as an
acceptance and a sponsor's request to sponsor a mentor as **Sponsor interest**; each has a name of
its own since. The institution's agreement emails, the rows sent before included, are named for the
Collaboration Agreement in full, such as **Collaboration Agreement accepted, to the institution**,
and the sponsor's five agreement emails say who receives them, such as **Collaboration Agreement
accepted, to the sponsor**.

The log never keeps the text of an email, its attachments, or anything secret such as a sign-in
code or a password link. Entries older than 30 days are deleted every day. WordPress's **Export
Personal Data** and **Erase Personal Data** tools include a person's entries, found by their
address. Erasing a person's entries is followed by WordPress's own confirmation email to that
address, and the log records that email too, so a fresh row for the address after an erasure is
expected. The emails the plugin had recorded before 1.122.18 (Settings > Mail showed the last 25 of
up to a hundred) move into the log, with their addresses masked as they were kept, no subject and no
recipient type; those older than 30 days are deleted with the rest.

### Mentor Status Checker

**WPCredits Program > Tools > Mentor Status Checker.** Reads the WordPress.org profile of each mentor
who has still to be made one, matches the Credits Mentor's Course completion, and reports what it
would change before it changes anything. It has two sources. First the applications selected on this
site, the newest selection first: where the course shows on an applicant's profile, they are made a
mentor on this site, once **Make mentors from applications** is on (see *Applications to mentor* in
the part on the plugin in wp-admin). Then, until **Stop checking mentors in Airtable** is ticked, the
mentors whose Airtable status is the one set under **Check mentors with status**, *Vetted -
positive* by default: where the course shows on one of those profiles, they are promoted to *Active*
in Airtable, as they were before 1.122.33.

It needs the Airtable connection only for Airtable's mentors. The applications selected on this site
are checked without it; while Airtable is not connected, the tool's status line and its screen say
that Airtable's mentors are not checked, and that a mentor made from an application gets no Airtable
row until it is. A run in which Airtable cannot be read still checks the site's applications, and
says above its results that Airtable's mentors were left out of it; with no application selected,
the error is the answer, as it was when Airtable was the only source.

An application's row in the results reads *Made a mentor* once it is, with what was held or not
written on the way (an invitation held, an Airtable row refused), and the tally counts **made a
mentor** beside **promoted**. A run that only reports, and any run while **Make mentors from
applications** is off, makes nobody a mentor: an application whose course shows reads *Awaiting
promotion*, "Ready to be made a mentor", or, while the switch is off, *Waiting*, with "it waits
until Make mentors from applications is switched on". A report keeps on each application the day of
its last check, which the Selected view shows. **Run check and promote** and **Promote all
eligible** promote and make mentors from both sources, and **Promote** on one row re-checks that
mentor or application first, never from the cache, and refuses an application that has left Selected
(course pending). A row that reads *Waiting* offers no **Promote**, since it says it waits; the
summary above the results counts those rows as waiting for Make mentors from applications, apart
from those awaiting promotion, as the tool's status line does, and **Promote all eligible** leaves
them out. Once the switch is on, the same rows await promotion again. One that **Promote all
eligible** finds has left Selected (course pending) since the run comes back as failed under the
applicant's name, with the reason.

#### Settings

At the top of the checker's screen, above the buttons that start a run, a form with its own **Save
settings**:

| Setting | What it is for |
| --- | --- |
| **Check mentors with status** and **Promote them to** | The Mentors table's status a run reads mentors at, *Vetted - positive* by default, and the one it moves them to, *Active*, each chosen from the table's Status options, or typed, with a sentence saying why, when those cannot be read. Writing the second needs the `data.records:write` scope on the token. |
| **Mentors in Airtable** | **Stop checking mentors in Airtable**, unticked by default: until it is ticked, the mentors holding the status above in Airtable are checked after the site's applications. Tick it once no mentor in Airtable holds that status. It is a stop and not a box to keep reading with, so that a save of this form from a page loaded before the box existed, which posts no box, leaves Airtable's mentors checked. |
| **Course title** and **Course slug** | The course a profile has to show as completed, the WordPress Credits Mentor's Course by default. The slug, from the course's address on learn.wordpress.org, is the reliable signal, and the title only a fallback. Both are typed: learn.wordpress.org has no list to read them from. |
| **Completion phrase** | Words that must appear in the same profile history entry as the course, *Completed the course* by default, so somebody who merely blogged about the course is not counted. |
| **Profile history filter** | **Milestones only (faster)**, the default, or **All contributions**: course completions are always milestone entries, so the faster filter reads roughly 40% fewer pages. Its fold: switch to all contributions only if WordPress.org changes and completions stop being found. |
| **Maximum history pages per mentor** | From 1 to 100, 15 by default. A mentor whose history is longer is reported as "could not check", never as "not completed", since a false negative would leave them waiting. |
| **Mentors per batch** and **Delay between requests (ms)** | How hard a run reads WordPress.org, from 1 to 25 mentors a batch, three by default, and from 0 to 5000 ms between requests, none by default: each mentor can cost several requests, so smaller batches keep the screen responsive. |
| **Cache profile results for (seconds)** | How long a settled answer is kept, up to thirty days, twelve hours by default. A failed read is always tried again, and `0` keeps nothing. |
| **Daily check** | Two boxes, **Run the check automatically once a day** and **Let the daily check also promote mentors**. Both are off by default: an unattended promotion writes to the shared Airtable base and, where **Make mentors from applications** is on, makes mentors on this site and sends their invitations, so turn the second on deliberately. The check ran once a week until 1.122.4; a site that had the weekly run moves to the daily one by itself, its next run within the hour. |
| **Slack webhook** | The Slack incoming webhook the message after a promotion goes to, which decides the channel it lands in. Shown masked and never sent to the browser: leave it blank to keep the one saved, or type `remove` to take it away. Only a Slack incoming webhook address, as Slack gives it, is taken; anything else is refused with an error on the screen, and the one saved before stays. Its fold says how to get one: a Slack app in the workspace, with Incoming Webhooks switched on and a webhook added for the administrators' channel. |
| **Channel to add them to** | The channel the message asks the administrators to add the mentors to, as it is named in Slack, such as `#mentors`. Left empty, the message says "the mentors channel". |

#### The Slack message after a promotion

With a webhook saved, every action that promotes or makes a mentor sends **one** message once it has
finished: a run, whether the daily one, **Run check and promote** or `wp wpcredits check_mentors
--promote`; **Promote all eligible**; **Promote** on one row; or, since 1.122.33, **The course is
completed** on an application, which sends its message at once. It calls the channel (`@channel`),
says how many mentors were moved to the status they were promoted to and which channel to add them
to, and lists each one with a link to their WordPress.org profile and, where the profile shows one,
their Slack name, which is the name to type when adding them, shown as code so that an address typed
there is not a link. The line of a mentor made from an application ends *from an application on the
site*, and the count and the status in the first line cover them as they cover a promotion. The last
line says who promoted: *the daily check*, *WP-CLI* or the administrator who pressed the button. Only
mentors whose status was actually written, and applicants actually made mentors, are named: an
applicant is named as soon as the account is made, so one whose Airtable row was refused is named all
the same, and one filed as already a mentor is not. An action that moved nobody sends nothing, and a
long list goes in messages of 25. The message is in the site's language, whoever pressed the button.

A message Slack refuses, or that cannot reach Slack, is not lost. Its mentors are kept and sent with
the next message, and every run sends what is kept even when it promotes nobody, so with the daily
check on a mentor waits a day at most. A mentor promoted while another message was being sent goes
in a message of its own about a minute later. A promotion never waits on Slack: Airtable is written
first. The line under the buttons says when the last message went and how many it named, or why it
failed, and how many still wait. Promotions made while no webhook is saved are not announced later.

### Student Duplicate Finder

**WPCredits Program > Tools > Student Duplicate Finder.** Lists every student who has more than
one row in Students, Students Reports or Feedback in Airtable, proposes which rows to delete, and
deletes the ones you tick and confirm.

A second row usually comes from the Airtable automation that creates a student's Students Reports
and Feedback rows. It runs whenever a Students row starts matching its conditions and it never
checks the address, so it runs again for an address that already has rows: a student who did not
move forward applies again, or somebody sets a status back and the automation fires a second time.
Duplicated addresses are what break the update automations, which is the error you see in Airtable.

The finder clears duplicates up; it does not stop them being made. Stopping them at source is a
change to that Airtable automation, written up step by step in
[Stopping duplicate students at the source](https://wordpresseducation.org/duplicate-students-at-the-source/).

#### Delete duplicates here, not by hand in Airtable

This is the part worth reading twice. **The site stores Airtable record IDs**, and deleting the row
one of them names is not something Airtable can warn you about:

- a student's account holds the record ID of their **Students Reports** row and of their
  **Feedback** row, which is how their Student Report Card finds their program;
- a mentor's **call note** and a **booked call** hold the record ID of the student they are about;
- **audit log entries** hold the record IDs of what they describe.

Delete such a row in Airtable and the site is left pointing at nothing. For the student, that
means their Student Report Card stops showing their program, and there is no message anywhere
saying why, on either side.

The finder knows every one of those references. It reads them before it proposes anything, it
**refuses to delete a row that anything on the site points at**, and it checks again in the second
before it deletes. That is the whole reason to use it rather than the Airtable interface. If you
have already deleted rows by hand and a student now sees nothing, say so: the site can be pointed at
the surviving row again, but only by someone who knows which row that was.

#### What a scan does

A scan reads the three tables and writes nothing to Airtable, so running one is always safe.

- **It runs by itself every three hours**, offset from the four syncs so no two of them run in the
  same request.
- **Scan now** runs one while you watch, with a progress bar. Press it if you have just changed
  something in Airtable and want the list to catch up.
- **A scan that fails changes nothing**, whether Airtable did not answer or the site could not read
  which of its own records point at the rows. Its error appears above the list, and the last good
  list stays on screen, so a bad night never leaves you with an empty screen.
- **An address is the same student however it is typed.** Capitals and spaces before or after it
  make no difference, so a row whose address was typed with a space around it is listed, and can be
  deleted, like any other. When a student's rows spell the address differently, their card says so.
- Pressing **Scan now** while a scan is running does nothing except say so. **Review selection** is
  disabled while a scan is running, because the rows underneath the list are about to change.

#### Working through the list

1. Open the finder and read the tiles: how many students are duplicated, how many are **Ready**, how
   many **need a decision**, and how many rows are duplicated in each table.
2. Start with **Ready**. Press **Select all ready**, or tick students one at a time.
3. Move on to **needs a decision**. Each row that may go has its own checkbox and, beside it, the
   sentence saying why it was held back. Read the sentence and decide. Nothing is ever ticked for
   you here.
4. Press **Review selection**. Nothing has been deleted yet.
5. The confirmation lists exactly what will go, table by table, and lists anything it left out with
   the reason. Rows that would leave a student with no row in a table are left out here already,
   not only after you press Delete. Read it. This is the page to turn back from.
6. Press **Delete**. Only this button deletes anything.

Take it in small batches the first few times. One confirmation deletes at most 100 rows, and there
is no prize for clearing the list in one afternoon.

#### Why a row is held back

The finder keeps the newest row in each table and proposes the older ones, but only when the older
row has nothing worth keeping on it. When it does, you get the sentence instead of a proposal:

| What the screen says | What it means | What to do |
| --- | --- | --- |
| The site points at it | An account, survey, call note, booked call or audit entry names this row | Delete the other row instead. This row is not selectable at all |
| The site uses an older row for this student, so this newest one may be the copy to delete | The pointers are on an older row, so "newest" is the wrong answer here | Decide which row the student's history really lives on, and keep that one |
| Still at a live status | The older row is at a status the program counts as current | Do not delete it until you know why a live student has two rows |
| Records a graduation | The older row is where this student graduated | Keep it. Graduations are the record of the program working |
| Holds work fields | Grades, reflection posts, hours or the project are filled in on the older row | Keep it, or copy the values across first |
| Holds survey answers | Feedback or semester report consent is on this row | Keep it, or copy the answers across first |
| Has Total hours set | The older row carries an hours total | Keep it, or move the total to the row you keep |
| Has Notes in Airtable | Somebody wrote something on this row by hand | Read the note before deciding |
| The newest row has no institution, mentor, status or Course and this one does | The newer row is emptier than the older one | Fill the gap on the newer row first, then come back |
| Created in the same second | Two rows share a creation time, so "newest" decides nothing | Look at both and pick |
| The newest row is at a status that ended, so this one may be the row to keep | Somebody applied again and did not continue | Usually keep the older, live row |

A student is **Ready** only when every table's older rows are clean deletes. One held row anywhere
moves the whole student to **needs a decision**, which is why that group is the larger one.

#### What happens when you press Delete

- **Every row is read from Airtable again** and every question is asked again. A row that changed
  since the scan, that the site has started pointing at, or that is already gone is left out and
  named on the notice. A row has changed when it carries more work, answers or hours than the list
  showed, when a value that held it back was replaced (a grade, a note, a screenshot), or when it
  gained a reason to be kept that it did not have.
- **One delete runs at a time.** A Delete pressed while another is running deletes nothing and says
  so; review the selection again once the first has finished. After a delete that was cut off part
  of the way, by a timeout for instance, the same notice can show for up to five minutes from when
  that delete began.
- **If the site cannot read what on it points at the rows, nothing is deleted**, and the notice says
  so.
- **The last row an address has in a table is never deleted.** Whatever else happens, a student
  cannot be erased from a table entirely.
- **Rows go in order: Feedback, then Students Reports, then Students**, so a child row never
  outlives its parent.
- **A sealed copy of every row is written to the site first.** If the copy cannot be written, the
  row is not deleted.
- **If Airtable stops a delete part of the way**, the notice says what was deleted before it stopped,
  with Airtable's own message. The rows it did not confirm stay in the list and can be selected and
  deleted again; each still ends with one copy and one log entry.

#### Deleted rows, and the 30-day copy

The **Deleted rows** list under the students records who deleted which row, from which table, and
when. **View copy** shows that row's cells exactly as they were, so you can type them back into
Airtable by hand.

**There is no automatic restore.** Bringing a row back is a manual job, and a restored row gets a
new record ID, so anything that pointed at the old one still needs fixing. After 30 days the cells
are erased and the entry stays without a name or an address, as a permanent record that something
was deleted.

An entry whose delete Airtable did not confirm says so, and the daily run checks it again: a row
still in Airtable leaves the list, copy and all, since nothing was deleted, and a row that is gone
becomes an ordinary entry. A copy whose delete Airtable never confirmed is erased at 30 days too,
and its entry says the row may still be in Airtable. Look its record ID up there: if the row is
still there, the next scan lists it again.

#### Settings

**Deleting ships switched off.** Its one setting, **Deleting duplicates**, with its box **Let administrators delete the duplicated rows they select and confirm**, is in the Settings section at the top
of the finder's screen, above the list, with a Save of its own. Until an Administrator turns it on
there, the whole list is read-only: no checkbox can be ticked, **Review selection** is grayed out, and
the list says where deleting is turned on. Scanning and reading work either way, so the list is worth
looking at long before anyone decides to delete from it. The row's fold says what a delete does: it
removes rows from Students, Students Reports and Feedback in the shared base, and the finder keeps a
sealed copy of each row for 30 days.

Leaving it off between clean-ups is a reasonable habit, not a sign that something is wrong.

#### On the Administrator Dashboard

The **Duplicated students** tile counts the students the last scan listed, and its card links here.
A tile at zero means the last scan found nothing, not that no scan has run: the card says which.

### Track Builder

**WPCredits Program > Tools > Track Builder.** A program track is one Airtable status, one form on
the Student Report Card, a key chip in one color and, when the track follows one, a Learn course and
an hours target. Before the Track Builder, a new track was a plugin release. Now it is a draft you
write here, preview, publish to Airtable and switch on, with no developer in the loop.

Two things are worth knowing before you touch it. **Nothing reaches students until a track is
published**, and every notice and the preview say so. And **the four tracks the program runs today
are here too**, as the program's four original tracks, published from the day a site goes live; the
last part of this section is what makes them different from a track you make.

#### The track list

{{image:track-builder-list|In this sample list, the program's four tracks run from their definitions, and a track of your own, the Accessibility Track, stays a draft until you publish it.}}

One row per track, in the order they were made:

| Column | What it shows |
| --- | --- |
| Track | The track's name |
| Status | The Airtable status a student holds to be on this track |
| State | *Draft*, *Published*, *Unpublished changes* or *In the trash*; under it, a line when the last compile left the track out, and a line when a live track's status is missing from **Currently mentoring** in Settings |
| Students | How many synced students hold its status now |
| Last published | When, and by whom |
| Actions | **Edit**, **Duplicate**, **Preview**, **History** and **Publish** (**Publishing** once it is), then the buttons only some tracks get |

**New track** sits above the list. The only button a row gets sometimes is **Delete**, offered on a
track that was never published. Delete asks first and cannot be undone, and it deletes that draft and
nothing else. A track that was ever published keeps its row, because its columns and its status live
on in Airtable; the program's four original tracks are published from the moment a site exists, so
none of them is ever offered it.

#### Starting a track

Three ways, each ending on the new track's page, as a draft.

- **New track** asks for the three things two tracks can never share: the **Name**, the **Airtable
  status** and the **Key**, the short word on the chip, plus the **Learn course** link when the
  track follows one. A name, status or key another track holds is refused, even when that track is
  a draft, since publishing the draft would claim it. With a link, the course's lessons are listed
  beside the questions, and the name is taken from the course when it is left empty. The chip color
  is chosen for you, the first one no other track holds, drafts included. The form starts empty; the
  questions are added on the track's page.
- **Duplicate** copies every question of an existing track, the four original tracks included, and asks
  for a name, a status and a key of its own, refused as New track's are when another track holds
  them. The copy starts with no Learn course and no hours target; set them on its page. This is the
  usual way to start a track that resembles one you run: duplicate the 150-hour track and change what
  differs.
- *From a Learn course link* is New track with the link filled in. The lessons appear under the
  groups that are their modules, and each lesson offers **Add a question under this lesson**, which
  is how a form gets built lesson by lesson.

#### The track's page

The properties come first: **Name**, **Airtable status**, **Key**, **Learn course**, **Hours
target** and **Key chip color**, which is one of blue, cyan, teal, green, red, pink or purple. The
hours target may stay empty. **Save the track** saves the properties; the questions save themselves
as they are added, edited and moved. Save refuses a name, status or key another track holds, drafts
included, and a refused Save draws the boxes again as you left them.

{{image:track-builder-track|On a track's page you set its properties and build its form group by group and lesson by lesson, and a line above the questions says what publishing would create in Airtable.}}

The **Learn course** row shows what the link resolved to, the course's title and number. **Read the
course again** asks Learn afresh; otherwise the site keeps a day's reading, so a lesson renamed on
Learn shows up here within a day. When Learn cannot be reached, the link the track already has is
kept with a warning, the lessons cannot be listed, and the track still saves and publishes. A new
link is not taken while Learn cannot be reached, and the notice says so; the box keeps the link you
typed, so pressing **Save the track** again once Learn answers takes it.

Changing the link to another course matches every question that carried a lesson against the new
course's lessons by its heading. Only a lesson's first question carries the lesson's title as its
heading, so the other questions of that lesson follow it; only the questions of a lesson none of
whose questions matched are left with no lesson. The notice names the questions matched and the
ones that no longer point at a lesson.

Then **Questions**, by group: Total hours, Onboarding, Project and Wrap-up, the four parts of the
Student Report Card's form. Total hours holds one question, Hours, which students see as the hours
box. Any other question goes in Onboarding, Project or Wrap-up: the question screen offers Total
hours to Hours alone, and Save and Publish refuse anything else. On a track with no Learn course,
students see the hours box in a section of its own, **My hours**.

Each question is a row with what the student reads, its Airtable column and its control, and beside
it **Edit**, **Move up**, **Move down** and **Remove**. The arrows move a question at once; if the
site cannot keep a move, the question goes back and a notice above the list says why. Remove takes
the question off the form; the column, and whatever students wrote in it, stay in Airtable, and the
confirmation says so.

Each group ends with **Add a question**: the Airtable column, what the student reads, the control and,
when the group's module has lessons Learn answered, **Under lesson**. Total hours offers it only
while the track has no Hours question. Adding opens the new question's page.

When the track follows a course, the lessons of each group's module are listed under the group's
questions, with a count such as *Lessons on Learn: 3 of 13 have questions.* Each lesson names the
questions that report on it, or offers **Add a question under this lesson**. A question added that way
is placed after the lesson's last question, carries the lesson, and takes the lesson's title as its
heading when it is the lesson's first question.

A line above the questions says what publishing would create in Airtable, *This track needs no new
Airtable columns.* or *Publishing will create 3 columns in Airtable.*, and how long ago the base was
read.

#### Controls and columns

Ten controls: Text, one line; Text, many lines; Rich text; Web address; Email address; Number;
Checkbox; One choice of several; Screenshot; Contribution team. The control decides the Airtable
column type: a new column is created with the type the control needs, and a column that already
exists has to be of that type, or the publish screen refuses.

A duplicated track shares its columns with the track it came from, and the question's page says so,
naming every other track that writes the column. Rewording a shared question keeps the column.
Changing its control, or the choices of a select, gives it a column of its own, named after the track,
which can still be renamed until the track is published. A different control is a different column.

#### One question

Each question has a page of its own: the column and the control at the top, then the rows that apply
to that control.

{{image:track-builder-question|On a question's page you set its column and its control, then the rows that apply to that control, here for a required line of text under a Learn lesson.}}

| Row | What it does |
| --- | --- |
| What the student reads | The label above the box |
| Group | Onboarding, Project or Wrap-up; Total hours for the Hours question, which goes in no other group |
| Help under the box | A sentence under the control |
| Heading before it | A heading printed before this question; the first question under a lesson carries the lesson's title here |
| Subheading before it | A second heading before this question, drawn like the first |
| Note after the run | A sentence after the run of questions this one ends |
| Row, and Shares one column of its row | Questions with the same row name sit side by side |
| Marked required | Draws the word *Required* beside the label; nothing is enforced |
| Kept off everything an institution reads | The answer is left off the Student Report Card an institution sees |
| Lowest value, Highest value, Step | A number's limits; the step also sets how many decimal places a new column keeps |
| Length limit | A single-line box's limit; a text area has its own |
| Monospace, for code | A text area drawn in a monospace face |
| Choices, one a line | The choices of a select; a column that already exists must offer every one of them |
| Learn lesson | The lesson this question reports on: the course's lessons by module, *None* first, or a number box when Learn cannot be read |
| Developer note | Why a column name looks like a slip; no student sees it |

**Save the question** returns to the track. A refusal redraws the question with what was typed, and
the reason.

**Once a track is published, its questions are locked**: the column, the control and the choices are
fixed, because the column in Airtable holds what students wrote, in that shape. The words can still
change. To ask something differently, remove the question and add a new one with a column of its own.

#### Preview and History

**Preview** draws the draft's form as a student sees it: empty answers, no student's record, the same
renderer and the same stylesheet as the Student Report Card. Nothing typed there is kept. The hours
box sits where the Student Report Card puts it: with the button that opens the course when the track
has a Learn course, side by side on the student's page and one under the other here in wp-admin, and
on its own when the track has none.

{{image:track-builder-preview|Preview draws the form a student on the track fills in, the same questions in the same order, and nothing you type there is kept.}}

**History** has three parts: what publishing would change, the published copy against the draft;
every save, newest first and at most twenty, with who saved it, when, and what changed since the one
before; and the publish log, every publish, unpublish, switch, column created and checklist item
ticked, with who and when.

{{image:track-builder-history|History shows you what publishing would change, every save with who made it, and the publish log, here with sample entries for one of the program's four tracks.}}

#### Publishing

**Publish** opens the publish screen. Nothing happens until the button at the bottom is pressed; the
screen first reads the base and says what it found.

{{image:track-builder-publish|Before you publish, the screen lists the columns it will create and the three steps only you can take in Airtable, then asks you to type the track's name.}}

- **This track cannot be published yet** lists what stops it: a column in Airtable with a question's
  name but another type, a control that cannot have a column created for it, a table that would pass
  Airtable's limit of 500 columns, a status, key or name another track holds, a draft's included, a
  question other than Hours in Total hours or Hours in another group, and a **Students Reports
  table** setting that holds anything but the table's ID, its name for example. **Check it against
  Airtable** refuses the same way while that setting is wrong.
- **Worth knowing before you publish** lists what does not stop it: a choice of the Status column on
  Students Reports or Students that nearly matches the track's status but not exactly, which the
  syncs would never match; a Learn course link that does not resolve; a table past 450 columns. A
  choice that is missing altogether is not a warning: the third checklist item below says, for each
  table, whether it has the choice yet.
- **Columns**: every column the track writes to, and whether it exists or will be created. With a
  schema token, the optional second token on the Connection tab of **WPCredits Program > Settings**,
  the site creates the missing columns when you publish. Without one, the screen lists the exact
  columns to create by hand, name and type, and Publish waits until the next reading finds them.
- **What the site cannot do**: the three Airtable steps no token can take, with the exact values to
  use. Add the status to the condition of the automation *Add students to Students Reports and
  Feedback*; create the track's welcome email automation, as each of the four tracks has one; add the
  status as a choice of the Status column on both tables. Tick each with **I have done this** once it
  is done, and the tick records who and when. An unticked item never blocks publishing, but until
  the automation item is ticked, students cannot be put on the track from the institution import,
  because they would never get a report row.
- Publishing any track adds its status to **Currently mentoring** in Settings, unless it is there
  already. The four original tracks' statuses are there from the start, so publishing one of them
  adds nothing new. A Settings save that would take a live track's status out saves everything else,
  leaves Currently mentoring as it was, and names the track.

When publishing would create columns in Airtable, Publish comes with a box: type the track's name
exactly as it is written, then press the button. Capitals count; spaces around the name do not. The
site can never remove a column it created, so a press whose text is not the name creates nothing and
says so. The name confirms the columns the screen lists: if the columns publishing would create
change before the button is pressed, nothing is published and the screen comes back with the new
list to read and confirm again. With nothing to create, Publish is one press; a press that finds
columns gone from the base since the page was drawn creates nothing, and the screen comes back to
show them.

Publishing runs its steps one at a time and records each. If Airtable refuses part-way, the notice
carries Airtable's own message, and pressing Publish again picks up at the first step not done. If
the page stops part-way instead, because the server gave up on a long run, the columns made so far
are already recorded, but for up to twenty minutes Publish on every track says another track is
being published. After that, press Publish again: it creates only the columns the base still lacks,
and the log names every column the site made.

What goes live is the track as it stood when Publish was pressed. An edit saved while Publish runs,
in another tab or by another Administrator, is not part of that publish: the track then
shows *Unpublished changes* until **Publish the changes** is pressed.

After publishing, the same screen offers **Check it against Airtable**, which reads the base again and
says whether every column is still there with its type and the status is a choice on both tables, and,
for every track but the program's four original ones, **Take it off the live site**. The four never
offer it: a line beside the other actions says the program's original tracks always run, and to edit
the track and publish the change instead. Unpublishing any other track is refused while any student
holds the status, with the count; otherwise the track becomes a draft again, nothing in Airtable
changes, and the status stays in Currently mentoring: removing it there takes the Student role from
everybody on the track, which is a decision of its own.

Editing a published track's words makes it *Unpublished changes*; students keep the published copy
until **Publish the changes** is pressed.

#### The four original tracks

The 150-hour, 50-hour, Developer and Designer tracks are the program's own four. Each has run from its
definition in the Track Builder since 22 September 2026, and there is nothing left to switch between:
no hand-written form and no plugin update can move one of them, and you edit, duplicate, preview and
publish them exactly like a track you make yourself.

A fresh site seeds and publishes all four at once, with their publish checklist already ticked, from
the seed files the plugin ships, so the program runs on day one with nobody pressing Publish. If one
of the four is ever missing, `wp wpcredits seed_tracks` on the command line puts it back; it is the
same command a site runs on itself the first time it reaches the Track Builder.

**Their statuses and keys belong to them.** No other track can take the Airtable status or the key of
one of the four, whether you are starting a new track, duplicating one, or saving one you already
have: the refusal names which of the four original tracks keeps it. And the four cannot be deleted or
unpublished at all; if something about one of them needs to change, edit the track and publish the
change instead.

A site already on an earlier version upgrades once, on its first load after 1.116.0 arrives: nothing
for you to press, and a load that is cut short partway is finished by the next one. On a fresh site a
seeding cut short leaves no half-made track behind, and `wp wpcredits seed_tracks` puts back any of
the four that is missing, then as at any other time.

### Need help?

**WPCredits Program > Tools > Need help?** The question box over the WordPress documentation: its
settings at the top of the screen, then how answers are produced, with the provider and the sites it
is told to use, and a box for trying a question as somebody on the program would ask it.

#### Settings

At the top of the screen, a form with its own **Save settings**. One sentence says what the question
box is, and its **Details** fold says where each question goes: the provider does the searching, so
nothing is stored on this site, and each question, and the pages found for it, go to that company.

- **Need help?** - its box, **Switch it on**, is on by default. Off means the box answers nobody, the
  header button disappears and the page it lives on is unpublished. Nothing is deleted, so switching
  it back on restores all of it.
- **Where answers come from** - the provider searches wordpress.org, make.wordpress.org,
  learn.wordpress.org and developer.wordpress.org itself. Nothing is copied to this site, so there is
  nothing to configure and nothing to refresh.
- **Answer provider** and **Provider API key** - leaving the provider as *None* keeps everything on
  this site and means there are no answers at all. Choosing one sends each question, and the extracts
  that match it, to that company. The key is stored in the database and never sent back to the
  browser; left blank, it keeps the current key. A key for the Gemini provider is free at
  aistudio.google.com.
- **Model** - typed, `gemini-flash-latest` by default: the provider lists no models to choose from,
  so the field holds whatever model is saved. Leave it as the default unless you have a reason not to.
  It is an alias that always points at the current Gemini Flash, so it cannot be retired out from
  under the site.
- **Who can ask** - mentors and administrators by default; optionally students and institutions as
  well, anybody logged in, or administrators only. Never anybody logged out, whatever this says.
  The documentation describes running the program rather than being on it, which is why students
  are not included by default.
- **Questions per person per hour** - from 0 to 200, 20 by default, so a free tier cannot be spent in
  an afternoon. Past the limit, people are asked to come back shortly. `0` removes the limit.

While it is switched off, a notice above the section says so, with a link down to the switch; while it
has no provider, the **Provider** row under the section says so, with a link back up. Its card on the
**Tools** screen says which of the two it is, never the Airtable connection, which Need help? does not
use. And a question that cannot be answered for want of a provider, a working model or a valid key
tells the person asking that an Administrator can put it right in the Settings section of the
Need help? screen, under **WPCredits Program > Tools**.
