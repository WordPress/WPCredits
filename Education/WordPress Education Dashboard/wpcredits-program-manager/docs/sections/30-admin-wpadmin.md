## The plugin in wp-admin

Everything the Administrator touches lives under one top-level menu, **WPCredits Program**. You
need the `wpcpm_manage_program` capability to see it, which the Administrator role is granted on
activation. Every screen under it is titled with its own name: **Overview**, a screen for each
audience, **Tools** and a screen for each tool, and **Settings**.

On the forms named here, a press locks the button at once and the button says what it is doing
until the next page opens, so a double press acts once: the decisions and the question on an opened
application, on the Sponsors and Institutions screens; on the Mentors screen, the decisions on an
application; on the Sponsors screen, **Run again** in the **Moving sponsors onto the site** box,
**Add the sponsor**, a sponsor's Edit form and its **Not a renewal**, a sponsor's **Attach
account**, **Remove** and posting switch, the offer and code forms, **Draft a report** and the forms
of a report's editor, and the agreement forms; on the Institutions screen, the signed agreement's
upload and withdrawal and the semester report buttons; **Save**, **Send me a test** and **Restore
the original** in
**WPCredits Program > Tools > Emails**; and the Track Builder's **Create the track** and
**Make the copy**. Every other form works as it always has, searching, filtering, a list's
**Apply** and **Screen Options** among them.

### The words the screens use

The screens, the settings and this guide call each thing by one name.

| Name | What it is | Where you meet it |
| --- | --- | --- |
| **WPCredits Program** | The plugin, as the menu names it. Each screen under the menu is titled with its own name alone: **Overview**, **Students**, **Tools**, **Settings** and so on. | The menu, and a screen's place in it, written with ">" between the steps, such as **WPCredits Program > Settings**. |
| **Audiences** | The people the program is for and the people who run it: **Students**, **Mentors**, **Institutions**, **Sponsors** and **Administrators**. Each audience has a user role and a screen under its name; the administrators hold WordPress's own Administrator role, which is granted the program's capabilities on activation. | The menu, each audience's own screen, and the Settings tabs. |
| **Tools** | The parts of the program that are run and configured on their own rather than belonging to one audience: **Header notices**, **Emails**, **Need help?**, the **Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**, each called by its name alone. | The **Tools** menu item and screen, and the Overview's **Tools** card, which lists each tool with its status. |
| **Landing page** | Where an account goes when it logs in: the **Mentor**, **Student**, **Institution** and **Sponsor landing page**, each with the page's address under its switch. | The Students and mentors, Institutions and Sponsors tabs of Settings. |
| **Remove** and **Leave** | The two answers of each rule for somebody who leaves the program, always in this order: remove the role or the access, or leave it in place. Nothing is ever deleted either way. | The Settings sections **When someone leaves**, **When an institution leaves the pipeline** and **When a sponsor is no longer Approved**. |

### Overview

{{image:admin-overview|The Overview screen: what waits for a decision, the syncs, the tools and the way to Settings.}}

**WPCredits Program** opens on the Overview: four cards with what waits for a decision, when each
sync last ran and runs next, whether each tool can run, and the way to Settings. The Overview only
reads and links; it decides nothing.

If Airtable is not connected yet, this screen says so and links straight to the setting.

**Waiting for a decision** lists each queue with something in it, in the order of the Administrator
Dashboard's strip of counts, each as the strip reads its tile: the count first, set apart, then the
strip's name for the queue, such as "2 Collaboration Agreements to review, 1 overdue". The count is
the strip's own, so past 200 the sponsor posts and Sponsor Collaboration Agreements to review read
200+. Each is a link to where its queue is listed: the institution applications, agreements and
mentor requests open the **Waiting for review** tab of the **Institutions** screen, the semester
reports to review and the semesters due for drafting its **Semester reports** tab, and the locked
accounts its **Accounts** tab; the mentor applications open the **Waiting for review** tab of the
**Mentors** screen; the sponsor applications, posts, events and agreements open the
**Waiting for review** tab of the **Sponsors** screen, each at the card that lists it, the sponsor
reports to finish its **Reports** tab, and the offers running low its **Offers and codes** tab; the
duplicated students open the **Student Duplicate Finder**; and the syncs that need a look open this
screen's own **Syncs** card. The dashboard's strip draws a tile only for a count above zero, and
this card likewise leaves out a queue with nothing in it; when nothing is waiting it says "Nothing
is waiting for an Administrator right now." At the foot of the card, **Decide on the Administrator Dashboard** opens that
page, where the decisions are made, except those on the mentor applications, which are made on the
Mentors screen; while the page is missing, the card says so in the button's place and asks you to
re-activate the plugin to recreate it.

**Syncs** has a row for each audience's sync, **Students**, **Mentors** and **Institutions**, each
name a link to the screen the sync is run from: the **Sync** tab of the Students and Mentors
screens and the **Sync and storage** tab of the Institutions screen. **Last run** gives the date and
time of the last run, in the site's own formats, or *Never*. **Next run** gives the date and time of
the next run; it says *Not scheduled* when none is, and *Running now* while a run is under way. The
audiences' rows show no errors: a sync's last error is on the Administrator Dashboard's **Syncs and
health** card. The fourth row, **Outcome figures** (1.122.24), is the weekly read of the three
figures the site counts for its homepage with the WordPress Credits impact dashboard's definitions,
every Monday at 07:00 UTC after the dashboard's build: its name opens the **Connection** tab of
Settings, **Last run** is when the stored figures were read, a line under it says why the last
attempt was held or failed, that it waits for the dashboard's build, that the site is not connected,
which figure the homepage leaves out for having fewer than 30 answers or outcomes, or that the
homepage shows none because the figures stored are more than 29 days old, and
`wp wpcredits outcome_figures` shows them, reads them again with `--refresh` and checks them with
`--refresh --dry-run`. The weekly read never stores the first figures: it holds them, and the line
says to compare the counts with the dashboard's with
`wp wpcredits outcome_figures --refresh --dry-run` and then store them with
`wp wpcredits outcome_figures --refresh`. On the Administrator Dashboard's **Syncs and health** card
the same line stands under the column headed **Last error**, and it is not always an error there:
*Waiting for the dashboard's Monday build.*, *Not connected.* and the note about fewer than 30
answers are notes, not errors.

**Tools** has a row for each tool, its name a link to the tool's screen, and under **Status** the
line the tool's card shows on the **Tools** screen; for a tool that cannot run, that line is the
warning that says why, in the tool's own words.

**Settings** says "Airtable is connected." or "Airtable is not connected yet." Its
**Open Settings** button opens the Settings screen.

### The audience screens

| Screen | What it does |
| --- | --- |
| **Students** | Two tabs: **Accounts**, the invitations and every Student account in one list, and **Sync**, the students sync and its last report. |
| **Mentors** | Four tabs: **Waiting for review**, the applications to mentor that came through the form on the site, read and decided here, with the applicants made mentors under **Activated**, the tab the screen opens on while any wait; **Accounts**, the invitations and every Mentor account in one list; **Engagement**, how each mentor uses the site; and **Sync**, the mentors sync, a warning while mentors made from applications wait for their Airtable row, and its last report. |
| **Institutions** | Six tabs: **Waiting for review**, the applications, signed agreements and requests from institutions waiting, read here and decided on the Administrator Dashboard; **Pipeline**, every institution record by stage and the consent report; **Accounts**, the invitations, every institution account in one list, the Confirmed institutions with no account yet, and each institution's members; **Semester reports**, every semester report and the semesters due for drafting; **Agreements**, the agreements to record as on file, the ones Airtable disagrees with, and the plugin's copy of the Collaboration Agreement; and **Sync and storage**, the institutions sync, the reconciliation of Students with Students Reports, and the check of how the host serves the private files. |
| **Sponsors** | Eight tabs: **Waiting for review**, the sponsor applications, sponsor posts, sponsor offers and the changes to them, sponsor events while they are on, and signed agreements waiting, read here and decided on the Administrator Dashboard; **Sponsors**, the box that finishes the move of the sponsor records onto the site while it is incomplete, then every sponsor with its status, tier, product, program contact, accounts and logo, **Edit** and **Manage accounts** on each row, and the form that adds a sponsor; **Accounts**, the invitations, every sponsor account in one list, the Approved sponsors with no account yet, where their accounts are created, and each sponsor's accounts; **Offers and codes**, every offer with its codes and claims; **Interests**, what sponsors said they would like to support; **Reports**, each sponsor's report and the editor it is written in; **Agreements**, the standing state of every Approved sponsor's agreement; and **Handbook listing**, the sponsors' listing for the handbook, in the bar only while the sponsor tiers are announced. |
| **Administrators** | Two tabs, under the button to the Administrator Dashboard while that page exists: **Accounts**, every administrator account in one list, and **Capabilities**, the program capabilities granted to Administrator. |

Since 1.92.0 the Administrator Dashboard on the front end gathers the queues these screens hold, apart from the mentor applications, which it counts (since 1.122.31) and which are decided on the Mentors screen; the Administrators screen links to it.

Since 1.93.0 the Sponsors screen is no longer a placeholder: it holds every sponsor with its status, program contact and accounts, the **Create account** and **Attach account** controls, on its **Accounts** tab since 1.122.0, and the log of interests sponsors expressed on their Sponsor Dashboard. Since 1.111.0 the sponsors' records live on this site, added with **Add a sponsor** and changed with **Edit**, both now on the **Sponsors** tab. An approved application creates its sponsor's account; any other sponsor's account is created on the **Accounts** tab's **No account** view, and nowhere else.

Since 1.97.0 the Sponsors screen also holds the queue of companies that applied through the form on the site, and its menu entry carries a bubble counting the applications, the signed agreements and the sponsor posts waiting for an Administrator, and since 1.123.0, while **Sponsor tiers** is on, the events waiting too, and since 1.123.18 the sponsor offers and the changes to them waiting for review. Since 1.122.0 that queue is read on the screen's **Waiting for review** tab and decided on the Administrator Dashboard, as the institution queue is.

Since 1.118.0 the Students screen is two tabs, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Students** opens on, holds the **Invitations** card and the **Student accounts** list. **Sync** holds the last sync's error when it ended in one, the **Airtable sync** card with **Sync students now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.

The **Student accounts** list works like WordPress's own **Users** screen: every Student account, a page at a time, so each one can be reached however many the site has. **Search students** finds an account by name, username, email address or institution. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. The list opens sorted by name, A to Z, so a first press on the **Student** heading turns it Z to A; a press on **Username** or **Institution** sorts the list by it, A to Z, and pressing a heading again reverses the order. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Student** column cannot be hidden, because each row's actions sit under it. The institution picker above the table, each institution with its number of accounts, narrows the list to one institution, and the **Invitations** card follows it, so its button invites only the students at that institution who have never been invited, and comes back to that institution's list. A student who also mentors and whose invitation was held when an application made them a mentor is left out of the button, and the card says how many it left out: only that student's own row sends it (see *Becoming a mentor*). The picker, a search by institution and the **Institution** sort work on the whole list, never only on the page in view.

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens that student's Student Report Card, and **Send invite** or **Resend invite**, which emails that one student at once. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**. On WordPress 6.7 or later, on this list and on every other accounts list, the action chosen under **Bulk actions** runs only when **Apply** is pressed: a search sent with rows ticked and an action chosen comes back to the list with nothing done to them, and Enter pressed in the menu does not apply it. Below 6.7, WordPress's own behavior stands. **Send invite** queues a first invitation for each ticked account that has never had one, as the card's button does, and the card shows the progress. **Resend invite** queues a fresh invitation for each ticked account invited before, which goes out with the next batch: the card's progress bar and its stop button follow a run of first invitations, and a re-invitation starts no run, so on its own it shows on neither. A row's invitation and the bulk actions both come back to the list as it was: the same view, search, institution, sort and page. However it is asked for, nobody is sent a second invitation within fifteen minutes of the last one: **Resend invite** on ticked accounts leaves out anybody invited in the last fifteen minutes, and its notice says how many it left out. **Send invite** and **Resend invite** on ticked accounts both leave out an account whose invitation was held when an application made it a mentor, and the notice says how many they left out: only that account's own row sends it, once an Administrator has confirmed the account is the person's.

In 1.119.0 the Mentors screen became two tabs in the same way, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Mentors** opens on while no application to mentor waits (see *Applications to mentor*), holds the **Invitations** card and the **Mentor accounts** list. **Sync** holds, since 1.122.33, a warning while mentors made from applications have no Airtable row yet, with the way to where each is written, the last sync's error when it ended in one, the warning while institution and team names have not been read yet, the **Airtable sync** card with **Sync mentors now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.

Since 1.122.26 the Mentors screen has a third tab between them, **Engagement**. It opens with the figures the Administrator Dashboard's **Mentor engagement** card shows, then lists every mentor: **Status**, whether Airtable holds them as Active (and **Administrator** for an Administrator who also mentors), their **Students** now and before, their **Last visit** (the latest day they opened a page of the site while signed in, which the site notes once a day) and **Last sign-in**, their **Schedule** (weekly hours and a meeting link, or what is missing), the **Notes** they wrote, their one-to-one **Calls** held and to come with how many held calls have a note, and their **Group sessions** held and to come. The 7 days and the 30 days count whichever is later, a mentor's last sign-in or last visit, so a mentor on a remembered session is not shown as absent; a mentor for whom the site holds neither is counted as never signed in. Sort by **Mentor**, **Students**, **Last visit**, **Last sign-in**, **Notes** or **Calls**; the list opens with the most students first. **Search mentors** finds a mentor by name, username or email address. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Mentor** column cannot be hidden. The views, each with its count, find who to follow up with: **All**, **Active**, **With students now**, **Never signed in**, **Not seen in 30 days**, **No program action** (no note, no saved schedule, no group session created and no call they canceled, including those who never signed in, so it can list more mentors than the card's count of those who signed in without one), **Students, no weekly hours** and **Administrators**. Every view but **All** and **Administrators** lists active mentors only, those Airtable holds as Active, and so do the figures above, except the call and group session figures, which count every call and group session on the site; an Administrator who also mentors is counted apart, and **Administrators** lists the active ones. **All** lists every mentor, active or not. The tab reads only. Sign-ins from before the release were read once from what the site already held and are approximate, and visits start with the release, so a date can read **Not recorded yet**; the note under the list gives the date of the reading. The site records the last sign-in and last visit of every account, not only mentors', and uninstalling the plugin removes them. `wp wpcredits mentor_engagement --summary` prints the same figures on the command line, and without `--summary` it prints one row per mentor, as a table, JSON or CSV; it writes nothing.

The **Mentor accounts** list works as the **Student accounts** list does: every Mentor account, a page at a time. **Search mentors** finds an account by name, username or email address. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. **No students**, the fourth view, with its count too, lists the mentors whose **Students** column shows 0: no current student, whether every student they had has finished or they never had one. The search, the sorts and the pages work in it as in the other views, and when every mentor has a current student it says so. The list opens sorted by name, A to Z, so a first press on the **Mentor** heading turns it Z to A, and **Username** sorts it by username, A to Z. **Students** sorts it by how many current students a mentor has, the fewest first, and **Status** puts **Active** before **Not in Airtable**; pressing a heading again reverses the order. Every sort works on the whole list, never only on the page in view. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Mentor** column cannot be hidden, because each row's actions sit under it. The list has no institution picker, so the **Invitations** card counts, and its button invites, every mentor who has never been invited, except one whose invitation was held when an application made them a mentor: the card says how many it left out, and only that mentor's own row sends it (see *Becoming a mentor*).

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens that mentor's Mentor Report Card, and **Send invite** or **Resend invite**, which emails that one mentor at once. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**; both work as they do on the Students screen, through the same queue. A row's invitation and the bulk actions both come back to the list as it was: the same view, search, sort and page. Here too nobody is sent a second invitation within fifteen minutes of the last one, and **Resend invite** on ticked accounts says how many it left out; both bulk actions leave out a mentor whose invitation was held, and say how many.

Since 1.119.0 the Administrators screen is two tabs too, **Accounts** and **Capabilities**, under the **Open the Administrator Dashboard** button, which stays above them whichever tab is shown. The button is there while that page exists; while it is missing, the **Administrator accounts** list says so. **Accounts**, the tab **WPCredits Program > Administrators** opens on, holds the **Administrator accounts** list: every account with WordPress's Administrator role, a page at a time. **Search administrators** finds an account by name, username or email address. The list opens sorted by name, A to Z, so a first press on the **Name** heading turns it Z to A; a press on **Username** sorts it by username, A to Z, and pressing a heading again reverses the order. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Name** column cannot be hidden, because each row's **Edit**, which opens the account in wp-admin, sits under it. **Can manage program** says whether each account holds the program's capability.

The list has one view, **All**, and no checkboxes, bulk actions or invitations: Administrators use WordPress's own Administrator role, the plugin never invites them, and their accounts are added and managed on the **Users** screen. **Capabilities** lists the program capabilities the Administrator role is granted on activation, as the screen did before.

### Applications to mentor

Since 1.122.31 a person can apply to mentor on this site, at /become-a-mentor/, and an Administrator
decides on each application on the **Waiting for review** tab of the **Mentors** screen. The form is
off until **Take mentor applications through the form on this site** is switched on, under
**Mentor applications** on the Students and mentors tab of Settings. While it is off, or while the
site has no published privacy policy or the syncs have read no countries or no contribution teams
yet, the page shows the public nothing, and an Administrator who opens it one sentence saying why.
The page is not gated: the people it is for have no account. The form writes nothing to Airtable.
Since 1.122.33 a selected applicant who completes the mentors' course becomes a mentor on this site
once **Make mentors from applications** is switched on, under **Mentor applications** on the
Students and mentors tab of Settings, as *Becoming a mentor*, further down, describes. It is off
until the launch, and while it is off an application stops at the mentors' course, as it did before
1.122.33, and nothing is written to Airtable.

The page the plugin makes starts with the Airtable form's introduction, two paragraphs above the
form, and they are the page's to edit. So is the sentence an applicant reads once the form is sent:
**Thank-you sentence**, in the **Thank you** panel of the **Mentor Application Form** block (or
`thanks="..."` on the `[wpcpm_mentor_application]` shortcode), and while it is empty the plugin's
own sentence is shown. A page already at /become-a-mentor/ when the plugin first looks is used as
it is, so the form shows on it only once the block is added.

The form asks the eleven questions the Airtable form asks, in its order: the applicant's full name;
their email; their WordPress.org profile (the username, an @username or the address of their
profile on wordpress.org, kept as the username, and anything else refused with a sentence asking for
one of those); their country (one field: type a few letters and pick one); whether they are a
teacher at an institution taking part in the program; the languages they can mentor in; the
contribution areas they have expertise in, which may be left empty; the hours a week they can give,
from 1 to 40, kept to one decimal place; the compliance declaration, the GPL and trademark statement
with its two links, which must be ticked; anything else they would like us to know; and the privacy
policy confirmation. An answer to **Anything else you'd like us to know?** holding what WordPress's
cleaning would remove, a tag or a percent code, is refused: the form says so in a sentence that
tells the applicant how to keep every word, and hands the typing back in its box. The countries are
the ones the institution form offers and the contribution areas the teams a student picks from, both
read by the syncs; the languages are a fixed list in the plugin, since the site holds none. It is
guarded as the sponsor form is: a hidden field, a signed token, five submissions an hour per address
and forty a day for the whole site before the rest are held, consent as a precondition with what was
agreed to recorded, the comment disallowed list and the links counted, and a ceiling on
acknowledgements. An application giving the address or the WordPress.org username of another
application in the queue or waiting for the course, or of a mentor the site knows, is held with a
mark and never merged. The thank-you page shows the application's reference (MAPP-2026-0007), and
every application but one filed as spam is acknowledged by email with a link that confirms the
address, unless the day's ceiling on acknowledgements has been reached or the email could not be
handed to the mail queue; the addresses under **Who is mailed**, or every Administrator, are told of
each one the checks did not hold, with the applicant's name and profile and the way to the tab.

**WPCredits Program > Mentors** opens on **Waiting for review** while any application waits there,
and on **Accounts** while none does; the tab is first in the bar either way. It lists the
applications oldest first, fifty at a time, in six views, each with its count: **Waiting**, every
application not decided yet; **Asked for more information** and **Second opinion**, those two kinds
among them; **Selected (course pending)**, the applicants sent to the mentors' course;
**Activated**, the applicants made mentors, which orders its list differently (see *Becoming a
mentor*); and **Lapsed**. Under the list, in every view, **Recently decided** lists the fifty
applications most recently declined or filed as spam, newest first, until they are deleted. Each
application shows the name and reference, how long it has waited (for a selected one, the day it was
selected and the day it lapses; for an activated one, the day it was made a mentor; for a decided
one, the day it was declined or filed as spam), its marks (**held**, **possible duplicate**,
**already a mentor**, **address not confirmed** and **acknowledgement not sent**), every answer, its
history (each decision with its day, who took it and their note) and the decisions its state allows.
The Administrator Dashboard's strip counts the applications waiting, and its tile opens this tab.

A selected application also shows what the Mentor Status Checker last found on the applicant's
WordPress.org profile: *Not checked yet*, or the day of the last check and whether the course shows,
does not show yet, or the profile could not be read, and why. While **Make mentors from
applications** is off it says the applicant waits; and when the account for an applicant whose
course showed could not be made, it says on which day and why, and the application stays selected.

The decisions, each with a box for a note where it takes one; a note stays on this site and is
never sent:

- **Select** sends the selection email, with the link set under **Mentors' course invitation link**,
  and moves the application to **Selected (course pending)**. It is refused while that setting is
  empty, and it is offered on a lapsed application too, for an applicant who comes back, and on a
  declined one, for an applicant who appeals: an appeal is either a new application, if you ask
  them to apply again, or a note and Select on the same row.
- **Ask for more information** sends the request for more information; the application stays in the
  queue, under **Asked for more information**, with the day it was asked.
- **Second opinion** marks the application for a colleague and sends nothing. Its note, which says
  what to look at, is needed.
- **Decline** sends the decline email and moves the application to **Recently decided**.
- **Mark as spam** moves the application to **Recently decided** and sends nothing.
- **Put back in the queue**, on an application filed as spam, returns it to **Waiting**, with a line
  in its history. One that was never acknowledged, and so carries the mark **acknowledgement not
  sent**, is sent the acknowledgement then, once, with the link that confirms the address; one
  acknowledged when it came, marked as spam by mistake, is sent nothing.
- **Send the acknowledgement**, on an application still in the queue that carries the mark
  **acknowledgement not sent**, sends it now, once, with the link that confirms the address, and
  leaves the application where it is. It is for an application past the day's ceiling on
  acknowledgements, or whose hand-off to the mail queue was refused; one filed as spam is sent it by
  Put back in the queue. An application acknowledged already, or holding no address, is sent
  nothing, and two presses at one moment send it once.
- **The course is completed**, on a selected application, and offered only while **Make mentors
  from applications** is on: for an applicant an Administrator knows has completed the mentors'
  course, whatever the profile shows. Its note, how you know, is optional and never sent. It makes
  the applicant a mentor at once, by the Administrator who pressed it, as *Becoming a mentor*
  describes.
- **Withdraw**, on a selected application alone, moves it to **Lapsed** now and sends nothing. Its
  note, what the applicant wrote, is needed, and the public figures count it as lapsed. An
  application that another request is making a mentor at that moment is left as it is, and the
  press says so.
- **Write the Airtable row again**, on an activated application whose Mentors row Airtable refused
  or never finished, writes it as the activation did; it takes no note, and a second press makes no
  second row (see *Becoming a mentor*).

Select, Ask for more information and Decline wait until the applicant has confirmed the address with
the link in the acknowledgement: until then each is refused and says so. An application whose
address is never confirmed is closed with Mark as spam, and a spam application counts for nothing in
the public figures, so that applicant is not counted as having applied. An application marked
**acknowledgement not sent**, one the form's checks filed as spam, one past the day's ceiling on
acknowledgements, or one whose acknowledgement could not be handed to the mail queue when it was
submitted, was never sent the link, so its address cannot be confirmed and the three decisions that
email the applicant stay refused until it is: Put back in the queue sends it to an application filed
as spam, and Send the acknowledgement to one still in the queue, past the ceiling or after a refused
hand-off, and the mark goes either way. Select, Decline, Mark as spam, Withdraw, The course is
completed and Send the acknowledgement ask before they act. The five emails are the **Mentor
applications** area of **Tools > Emails**, where their words can be edited.

A selected application whose course has not shown after the days under **Selected applications
lapse after (days)**, 90 by default, moves to **Lapsed** by itself, with a line in its history, in
the daily run that also keeps the days under **Applications** on the Advanced tab: spam is
deleted after 30 days, and declined, lapsed and held applications after a year, by default. A
selected application that is being made a mentor at that moment is left to that and does not lapse.
The figures for the Credits Monthly Report and the WPCredits Tracker are public at
`/wp-json/wpcpm/v1/mentor-applications`: for each month since the first application, how many
applications were received, selected, activated (made a mentor), declined and lapsed, and how many
applications are open and how many selected today, with nothing about any person. An application
filed as spam is not counted as received. Each application
counts once per figure in a month: one selected twice in a month counts once there, a withdrawal and
a lapse in one month are one lapse, and one selected again in a later month, after a lapse or an
appeal, counts in both months, so the months are not to be added up as if each application counted
once. An applicant filed as already a mentor is not counted as made a mentor, and what happens to
the Airtable row counts for nothing.

#### Becoming a mentor

With **Make mentors from applications** on, a selected applicant becomes a mentor in one of two
ways: the Mentor Status Checker finds the mentors' course on their WordPress.org profile when it
promotes (the daily check does so when **Let the daily check also promote mentors** is ticked in the
tool's settings, and **Run check and promote** and `wp wpcredits check_mentors --promote` always
do), or an Administrator presses **The course is completed** on their application, which asks first.
It is one step either way, and the mentors sync makes a mentor the same way. It runs in this order,
so that a mentor is never kept waiting on Airtable:

1. **The account.** The applicant's account is the one that has their email address, else the one
   named after their WordPress.org username, else the one that holds their profile; where the
   Mentors row that holds their address is already linked to an account, that account is theirs.
   With none, an account is made, named after the username, with the Mentor role. An account that
   exists is linked and given the Mentor role besides its own: no role is taken away, and an
   Administrator's roles are never touched. A former mentor is a mentor again on their own account.
   If the account cannot be made, the application stays selected, with the reason on it.
2. **The application** moves to **Activated**, with a line in its history, and the Mentor Status
   Checker's Slack message names the new mentor.
3. **The invitation.** One invitation, which carries the welcome, is queued whatever **Invitation
   emails** says, and goes out with the queue, a few at a time. None goes to an Administrator's
   account, which signs in already, and none goes yet in two cases where the site cannot be sure
   whose address it would reach: when the account was found by the WordPress.org username alone,
   which may be somebody else's, and when the Mentors row that holds the applicant's address belongs
   to one account while another account also answered for the applicant. The mentor is made all the
   same. The notice after the press, the application's history and its row under Activated say the
   invitation is held and why, and once an Administrator has confirmed which account is the
   person's, **Send invite** or **Resend invite** on its row in the Mentors screen's **Accounts**
   tab sends it. Until then the **Invitations** card's button, **Send invite** and **Resend invite**
   on ticked accounts and the email queue pass the account over, and the card and the notice after a
   press say how many they left out.
4. **The Airtable row**, last. An account that holds its own Mentors row already, a former
   mentor's, has that row's Status set back to Active, the status the mentors sync reads, and no
   search is made; only if Airtable says that row is gone is the table searched, as for anyone
   else. Otherwise the Mentors table is searched for the applicant's address first. A row that
   holds it, whatever its Status, is set back to Active and linked to the account; only its Status
   changes. With none, one new row is written, with the applicant's name, WordPress.org profile,
   email and Status and their answers whose columns the table has, each shaped as its column takes
   it. A country, language or contribution area the base does not hold is left out by name, the
   application says so, and the rest of the row is written. Writing needs the token's
   `data.records:write` scope, and reading each column's type its `schema.bases:read` scope; where
   the token lacks the second, the row holds the name, profile, email and Status alone. Nothing else
   in Airtable is ever changed and nothing is deleted; after this the row is Airtable's, and the
   site never updates it again.

An applicant who is a mentor already, an account with the Mentor role, or an Administrator's, that
is active and linked to its Mentors row, is filed under **Activated** as already a mentor, with a
note in the history: nothing is sent, nothing is written to Airtable, and the public figures do not
count them as made a mentor. An Administrator who applied keeps their roles and is sent no
invitation. Two presses, or a press and the daily check, on one application at the same moment do
the step once: the second is told it is in hand. While **Make mentors from applications** is off
nothing is made, and an applicant whose course shows waits under Selected (course pending).

**Activated** lists the mentors made from applications. The applications still waiting for their
Airtable row come first, oldest first; the others follow, the newest mentor first. Fifty are drawn,
and a line says how many there are when there are more. Each row shows the account, linked to its
profile screen, and the invitation's fate and the Airtable row's. The invitation line counts from
the moment the applicant was made a mentor, never from an older invitation to the same address: it
says the invitation waits in the email queue; or was sent on a day; or was handed over on a day and
the mail log does not say whether it went; or that the site's mail host refused it, so that nothing
was sent and the link in any earlier invitation no longer works, with where Send invite or Resend
invite is; or that none has gone since, because it was held for a person to confirm the account or
was passed over, with where Send invite or Resend invite is. For an Administrator's account it says
none is sent, for an account that is gone that none can reach it, and for an applicant filed as
already a mentor it says nothing. The Airtable line says whether the row was written, set back to
Active or linked, and what was left out of it.

If Airtable refuses the row (a token without write access, a column that refuses a value, a table
that cannot be read, the network, or no Airtable connection at all), the mentor is made and invited
all the same. The application says Airtable's words, and **Write the Airtable row again** waits on
it under Activated. It searches the table by the address before it writes anything, so a row that a
request which died had made is found and never made twice. Meanwhile the mentors sync does not
unmake the mentor. If it links the account to an Active row itself, that ends the wait, and the
application then reads as linked by the mentors sync or by hand, with nothing more to write. An
account that is gone has nothing to write for, and the application says so. The Mentors screen's
**Sync** tab says how many mentors are waiting for their row.

#### Switching it on

**Make mentors from applications** ships off, and a person switches it on at the launch, in this
order:

1. Before the release that carries it is deployed, the mentor coordinator edits **Invitation:
   mentor** in **Tools > Emails**. Until the deploy the live editor shows the earlier text, with no
   welcome, so they add the welcome after the invitation's own lines, copied from the release's
   shipped text of that email, which shows it with one bracketed line where the private links go.
   They type the courses' invitation links and the mentor resource folder's link in where that line
   stands, and save. A saved edit is sent in place of the shipped text, welcome or none, so they
   check that the edit in place is the one they just saved, not an older one. **Contact address in
   emails** is set as well, since the welcome asks mentors to reply and a reply reaches that
   address. From the deploy on, every invitation the mentor's template sends carries the welcome: an
   activation's, one sent from the Mentors screen, and one the mentors sync queues while
   **Invitation emails** is on.
2. The person running the launch reads the Airtable base's automations, which the site cannot see,
   so that none fires on the check row of the next step.
3. They run `wp wpcredits mentor_airtable_row --check --email=<the test mailbox's address>`, by
   hand, once. It writes one synthetic row to the Mentors table, with that address and a Status that
   neither the mentors sync nor the Mentor Status Checker reads, reads it back, deletes it, and says
   which columns the base kept and which names it would leave out. It may be run against the live
   base because it only ever deletes the row it just wrote. It changes nothing on the site, and it
   ends in an error while a row may be left behind, which then has to be deleted in Airtable by
   hand, and while a column did not take what the site sends. A create that failed on its way to
   Airtable is said to have failed, never to have been refused, since it may still have reached the
   base. `wp wpcredits mentor_airtable_row <reference>` prints the row an application would write,
   with why the base's columns could not be read when they could not, and writes nothing; it prints
   the applicant's answers, so use it on a test application.
4. They switch **Make mentors from applications** on, and switch off the Airtable automation that
   emails the welcome, so that a new mentor is not welcomed twice.

### Institutions

The Institutions screen is six tabs, and a press on any of them comes back to it, with what came of it said above the tabs. **Waiting for review**, the tab **WPCredits Program > Institutions** opens on, holds the queue; the others are **Pipeline**, **Accounts**, **Semester reports**, **Agreements** and **Sync and storage**. While Airtable is not connected, every tab says so above the tabs, with the way to the setting. The menu entry carries a bubble counting the applications, the signed agreements and the requests from institutions waiting.

**Waiting for review** lists the applications from institutions, the signed agreements and the requests from institutions in one list, oldest first, each with how long it has waited and the country's person of contact, for information. An application or a signed agreement is marked *overdue* once it has waited longer than the days set under **Agreement review** on the Institutions tab of Settings, and a request from an institution once it has waited longer than fourteen days; an application the anti-spam checks held is marked *held*, and one that names the same institution or the same address as another in the queue *possible duplicate*. The queue is read here and decided on the Administrator Dashboard: every row ends with **Open on the Administrator Dashboard**, the way to the card there that decides it. An application's row also has **Open this application**, a mentor request's says who the mentor is wanted for and who asked, any other request's says what it asks, the student it is about and who raised it, and a signed agreement's row is the review block the dashboard draws, its checklist, what the scan noticed and the download link, with **Open on the Administrator Dashboard** where the dashboard's block has **Accept it** and **Return it with this note**. While the Administrator Dashboard's page is missing, the list says so once above the rows, and no row links there.

**Open this application** shows one application in the list's place, with **Back to the queue**: what the anti-spam checks noticed, whether the applicant has confirmed the address, every answer with the consent, and what Airtable already holds under the same name or address. Under **Where it is decided**, an open application that the Administrator Dashboard's **Institution applications** card lists says it is decided there, with the way there. That card lists the fifty oldest open applications, so one past them is decided here, with the same four decisions, **Approve**, **Send this question**, **Reject** and **Reject as spam**, and each press comes back to the queue. The record-keeping on a closed application stays here: one rejected or marked as spam has **Put back in the queue** and **Delete for good**, since the dashboard folds in only the oldest fifty of those, and an approved one has **Delete for good**, since the dashboard never lists it. **Delete for good** keeps only the application's reference and the date.

**Pipeline** lists every institution record by stage, as the last institutions sync read it, and says when that was. Each row gives the institution's country and city, whether Airtable holds a contact address for it, never the address itself, whether it carries the consent tick, when the record was created and the state of its agreement. **Confirmed with no agreement recorded** narrows the list to those institutions, and **Show every stage** brings the rest back. Above the stages, **Institutions with no live member** counts the institutions nobody can act for on this site, with the way to give one an account on the **Accounts** tab, and a line lists the countries named by institutions that have no Administrator contact in the Countries table. Under the list, **Consent** counts the records collected before the form asked for consent, and the ones created without the tick since, which is how a record entered by hand in Airtable looks.

**Accounts** holds the **Invitations** card, whose button invites every institution account never invited, except one that also mentors and whose invitation was held when an application made it a mentor (the card says how many it left out, and only that account's own row sends it), and the **Institution accounts** list, under a warning naming any institution account locked out of roster changes for the rest of the day, a lock that lifts by itself the next day. The list works as the **Student accounts** list does: every institution account, a page at a time, with no email address printed, though the search finds one. **Search institutions** finds an account by name, username, email address or the name of the institution it acts for. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. The list opens sorted by name, A to Z, so a first press on the **Name** heading turns it Z to A; **Username** and **Institution** sort it A to Z, **Member since** by the day the membership began, the earliest first, and **Status** puts **Active** before **Membership ended**, either followed by *locked today* for an account locked for the day; pressing a heading again reverses the order. Every sort works on the whole list, never only on the page in view. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Name** column cannot be hidden, because each row's actions sit under it.

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens the Institution Dashboard as the institution the account acts for, while that page exists, **Send invite** or **Resend invite**, which emails that one account at once, and **Manage members**. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**; both work as they do on the Students screen, through the same queue, and come back to the list as it was. Here too nobody is sent a second invitation within fifteen minutes of the last one, and **Resend invite** on ticked accounts says how many it left out.

**No account**, the fourth view, lists institutions rather than accounts: the Confirmed institutions that have no account yet, the ones ready for one first, then by name. Each is shown by its name over its Airtable record ID, with whether Airtable holds a contact address for it under **Contact**, and under **Account** either **Ready** or why it cannot be given one yet, in the words the institutions sync uses. **Search institutions** finds an institution here by its name, and **Institution** sorts the view by it. To create the missing accounts, tick the ready institutions, choose **Create account** under **Bulk actions** and press **Apply**, or press **Create account** under one institution's name. **View page**, under every institution's name here as under an account's, opens the Institution Dashboard as that institution, while that page exists. Each account is made from the Contact Email Airtable holds, with an invitation queued for it: a password-set link to that address, which cannot be recalled once sent, as the view says above the list. One press creates at most 25 accounts, and the rest stay listed. While any institution on the view has no agreement recorded, the view names it above the list, with the way to the **Agreements** tab, and no account is created in bulk until it is recorded. Under the list, the view says whether the institutions sync creates these accounts too, which the **Create accounts automatically** setting decides, and when the institution records it lists were last read.

**Manage members** is under every row of the **No account** view, and under an account's row after its invitation; for an account whose membership ended, it opens the institution the account left, where the account is listed with its **Re-add**. It shows that institution's members in the list's place, under the institution's name and **Back to the accounts**, which returns to the list as it stood when **Manage members** was opened until **Remove**, **Re-add** or **Add account** is pressed on the view, after which it opens the **Accounts** tab's first page; uploading or withdrawing a signed agreement there keeps the list as it stood. The view shows each member with **Remove**, which ends their access and keeps their account; the contact Airtable names, when no member holds that address; each former member with **Re-add**; and **Add an account**, a name and an email address. **Add account** creates a new account with a random password and queues its invitation. An address that already has an account is adopted only when that account was a member of this institution before or belongs to a mentor; any other is refused as a conflict, naming the account's username, since an account made by hand is somebody's to explain. An adopted or re-added account that was invited before is not sent another invitation, and the notice says so.

Under the members, a signed agreement that reached the program by email is uploaded on the institution's behalf, and lands in the review queue as the institution's own upload does. Invitations the institution's members have sent their colleagues and that are still pending are not on this view: they are on the Institution Dashboard, where they can be resent or canceled. Unlike the list, the view prints the members' email addresses, because accounts are added and removed by address. For a record the pipeline index does not hold, it says "No institution record has that ID." and points to the institutions sync on the **Sync and storage** tab.

**Semester reports** lists every report the site holds, the drafts first, then the sixty most recently edited approved ones: each with its institution, a link that opens the report on the Institution Dashboard as that institution, its semester, whether it is a draft or approved, when it was drafted and whether by the site or an Administrator, when it was last edited, and **Ask the students**, which writes to the semester's students who wrote feedback and have not said whether it may be used. Under the list, **Due for drafting** lists the semesters the nightly job would draft, each with **Draft now**; **Draft any semester** drafts one the job has not reached; and **Report log** lists the last twenty things that happened to a report.

**Agreements** holds three cards. **Agreements on file** lists every Confirmed institution with no agreement recorded, and its button records them all as signed in one press, with the Drive link to the folder where the signed copies are kept: each institution gets its own recorded agreement, its own line in the audit log and its own Airtable cells, and its account can then be created. **Agreement discrepancies** lists the institutions whose agreement the site and Airtable's Agreement Status disagree about, each locked until the two agree. **Agreement template** shows the plugin's copy of the Collaboration Agreement, its version, when it was copied from the Doc and its checksum, and which institutions signed which version.

**Sync and storage** holds the last sync's error when it ended in one, the **Airtable sync** card with **Sync institutions now**, or the run's progress and **Cancel sync** while one is going, then **Reconciliation**, the rows where the Students table and Students Reports disagree, as the last students sync found them, with the Students rows that name no institution and a **Link** for each where linking is safe, and **Storage**, which says how the signed agreements are kept and whether the host refuses a stranger's request for them, with **Run probe** to ask the host again.

### Sponsors

The Sponsors screen is eight tabs, and a press on any of them comes back to it, with what came of it said above the tabs. **Waiting for review**, the tab **WPCredits Program > Sponsors** opens on, holds the queue; the others are **Sponsors**, **Accounts**, **Offers and codes**, **Interests**, **Reports**, **Agreements** and **Handbook listing**, which is in the bar only while the sponsor tiers are announced, with **Sponsor tiers** on in Settings. The sponsors' records live on this site: they are listed, added and edited on the **Sponsors** tab, an approved application creates its sponsor's account and any other sponsor's account is created on the **Accounts** tab's **No account** view, and no tab waits for a connection to another service. The menu entry carries a bubble counting the sponsor applications, the signed agreements, the sponsor posts and the sponsor offers waiting and, while **Sponsor tiers** is on, the events waiting.

**Waiting for review** holds a card for each kind of item waiting, in the order the Administrator Dashboard draws them, each listing what waits oldest first: **Sponsor applications**, **Sponsor posts to review**, **Sponsor offers to review**, **Events**, only while **Sponsor tiers** is on in Settings, and **Signed agreements**. The queue is read here and decided on the Administrator Dashboard, and every item links to its own place there. **Sponsor applications** lists the companies that applied through the form on the site, each with how long it has waited, its website and contact, and its state. An application the anti-spam checks held is marked *held*, one that names the same company or the same address as another in the queue *possible duplicate*, one from a company the site already holds under the same name or website *already on the site*, one whose contact address already belongs to an account on this site *already an account*, and one whose approval stopped halfway *approval half done*. Each row has **Open this application** and **Open on the Administrator Dashboard**, the way to its own item on that page's **Sponsor applications** card, and under the list the same link opens that card. Under it, **Recently decided** lists the fifty most recently decided applications, marked as spam, rejected or approved, newest decision first, each with **Open this application**, where one is put back in the queue or deleted for good, and no link to the Administrator Dashboard, which lists open applications only.

**Sponsor posts to review** lists the posts sponsors wrote and submitted for review, each with its sponsor, who wrote it, when it was submitted and **Preview**, the way to read it first. **Sponsor offers to review** lists the offers sponsors sent for review and the changes they made to what students see of live ones, the codes they added to an approved offer among them, each saying whether it is a new offer or changes, whose offer it is, who sent it and how long ago. **Events** lists the co-hosted events sponsors planned that wait for the program's approval, each with its sponsor, who planned it, how long ago and when it takes place, on your own clock. **Signed agreements** lists each document a company uploaded as the review block a reviewer reads: who uploaded it, when and how large it is, what to read it for, what the scan noticed in the file and **Download the signed agreement**. Every post, offer, event and document ends with **Open on the Administrator Dashboard**, the way to its own item there, where a post is published or returned, an offer or a change approved or returned, an event approved or returned, and a document accepted with **Accept it** or returned with **Return it with this note**. **Accept it** asks before it acts: it names the company, says that the agreement is recorded as accepted with the day's date and how many people at the company are emailed, and that it can be taken out of force on this screen's **Agreements** tab. **Sponsor applications**, **Sponsor posts to review**, **Sponsor offers to review** and **Signed agreements** each list no more than the fifty oldest, the ones the dashboard's card lists, and while more are waiting, a line above the list says how many of them it shows and that the next take their place as these are decided on the Administrator Dashboard; on **Sponsor applications** it adds that the list stops there so that a burst of applications cannot make the screen too slow to open. **Events** lists every event that waits, as the Administrator Dashboard's card does. The posts, the offers, the events and the agreements are counted up to 200, and past that their count reads 200+. While the Administrator Dashboard's page is missing, the tab says so once above the cards, and nothing on it links there.

**Open this application** shows one application in the cards' place, with its state, when it arrived and **Back to the queue**: what the anti-spam checks made of it, every answer with the consent, the logo files, and what the site already holds under the same name or website. Under **Where it is decided**, an open application that the Administrator Dashboard's **Sponsor applications** card lists says it is decided there, with **Open on the Administrator Dashboard**, the way to its own item on that card. That card lists the fifty oldest open applications, so an open application past them keeps its decisions here, every decision its state allows, **Approve**, **Send this question**, **Reject** and **Reject as spam**, and **Put back in the queue** for one held or waiting on the applicant, and each press comes back to the queue. The record-keeping on a decided application stays here, since the dashboard lists open applications only: one rejected or marked as spam has **Put back in the queue** and **Delete for good**, and an approved one has **Delete for good**. **Delete for good** keeps only the application's reference and the date, and takes the logo files with it unless the application was approved, when they are the sponsor's logo. An application in a state no decision writes is told only that applications are decided on the Administrator Dashboard, with the way to that card, and while that page is missing, the opened application says so in the link's place.

**Sponsors** holds, while the move of the sponsor records onto the site is incomplete, the **Moving sponsors onto the site** box: how far the move got, the sponsors whose old Administrator matched no Administrator on this site, each to be given one on its Edit form, the sponsors still waiting for the first offer in draft that keeps the move open, and **Run again**. The move runs by itself, trying again at most once a minute, and the box goes once every sponsor has moved. Then the **Sponsors** card lists every sponsor on the site in a table, with its status, its tier, its product, its program contact, how many accounts act for it and whether its logo is on the site, and **Edit** and **Manage accounts** in each row's last cell. A sponsor's name opens the Sponsor Dashboard as that sponsor, while that page exists, and **Manage accounts** opens its accounts on the **Accounts** tab. **Edit** opens the sponsor's form in the card's place, a view of this tab, under **Back to the sponsors**: what an Administrator owns of the record, with **Save** and **Cancel**, then the sponsor's key and listing handle, which nobody edits. Right after Status, on the Edit form and the Add form alike, the box *Test sponsor: not listed publicly* makes the sponsor a test sponsor, left out of the Partners page, the logo strip, the handbook listing and the public feed, with its offers, posts and events shown to no student or mentor, while its own accounts see their Sponsor Dashboard as usual; the table marks its row "(test sponsor, not listed publicly)", and *Test sponsors (1.123.18)*, in the part of this guide on sponsors further on, says how to test with one. An address that names no sponsor draws the card under a notice that says so. Under the table, **Add a sponsor** is the form for a company that never applied on the site; nothing else happens when one is added, and the press opens the new sponsor's Edit form. Once its status is Approved, its account is made with **Create account** on the **Accounts** tab's **No account** view.

**Accounts** holds the **Invitations** card, whose button invites every sponsor account never invited, except one that also mentors and whose invitation was held when an application made it a mentor (the card says how many it left out, and only that account's own row sends it), and the **Sponsor accounts** list, under a warning naming any sponsor account locked out of changes for the rest of the day, a lock that lifts by itself the next day. The card and the list's **Send invite** and **Resend invite** are new for sponsor accounts in 1.122.0: before, a sponsor account was sent only the invitation queued when it was created or attached. The list works as the **Student accounts** list does: every sponsor account, a page at a time, with no email address printed, though the search finds one. **Search sponsor accounts** finds an account by name, username, email address or the name of the sponsor it acts for. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. **Sponsor** names the sponsor each account acts for, with the sponsor's status after it in parentheses when it is not Approved, since such a sponsor keeps its accounts until they are removed. The list opens sorted by name, A to Z, so a first press on the **Name** heading turns it Z to A; **Username** and **Sponsor** sort it A to Z, **Member since** by the day the membership began, the earliest first, and **Status** puts **Active** before **Membership ended**, either followed by *locked today* for an account locked for the day; pressing a heading again reverses the order. Every sort works on the whole list, never only on the page in view. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Name** column cannot be hidden, because each row's actions sit under it.

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens the Sponsor Dashboard as the sponsor the account acts for, while its membership lasts and that page exists, **Send invite** or **Resend invite**, which emails that one account at once, and **Manage accounts**, for an account whose **Sponsor** names one. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**; both work as they do on the Students screen, through the same queue, and come back to the list as it was. Here too nobody is sent a second invitation within fifteen minutes of the last one, and **Resend invite** on ticked accounts says how many it left out.

**No account**, the fourth view, lists sponsors rather than accounts: every Approved sponsor that no account acts for yet, the ones ready for one first, then by name. Each is shown by its name over its key, the one the site holds it by, with its contact address under **Contact**, masked, or *no email* when it has none, and under **Account** whether it is **Ready**, ready with "the account that already uses this address will be attached", or why it cannot be given one yet: the sponsor's record holds no contact address, which is added on its Edit form, or the address belongs to an administrator, who already reaches every sponsor. **Search sponsor accounts** finds a sponsor here by its name, and **Sponsor** sorts the view by it. To create the missing accounts, tick the ready sponsors, choose **Create account** under **Bulk actions** and press **Apply**, or press **Create account** under one sponsor's name; a sponsor that is not ready has no box to tick. Each account is made from the sponsor's contact address, or the account that already uses that address is attached, and an invitation is queued for it unless it was invited before, as the view says above the list while a sponsor on it is ready: "Creating an account emails a password-set link to the sponsor's contact address. An invitation cannot be recalled once sent." The sponsor's first offer is created in draft, and the sponsor's log has a line for it. One press creates at most 10 accounts, and the rest stay listed: when more were ticked, the notice says "One press creates at most 10; tick the rest and press again." It names any sponsor it could not give an account, with why. A ticked sponsor that has had its account since the page was drawn is left alone, and when that leaves no ticked sponsor to give one, the notice says "There was nothing to create."; **Create account** pressed under such a sponsor's name says "That sponsor already has an account." Under the list, the view says "An approved application creates its sponsor's account. For any other Approved sponsor, this view is the way one is made." Under each sponsor's name are also **View page**, which opens the Sponsor Dashboard as that sponsor, while that page exists, and **Manage accounts**.

**Manage accounts** is under every row of the **No account** view, under an account's row when its **Sponsor** names a sponsor, and in the last cell of each row of the **Sponsors** tab's table; an Administrator reaches the same view from the Sponsor Dashboard's **People** card. It shows that sponsor's accounts in the list's place, a view of this tab, under the sponsor's name and **Back to the accounts**, which returns to the list as it stood when **Manage accounts** was opened until something is pressed on the view, after which it opens the **Accounts** tab's first page, as it does for a view opened from the **Sponsors** tab or the **People** card. Every press on the view comes back to it. Each account is listed by name and email address with **Remove**, which ends its membership and its right to write posts, and keeps the account. For an Approved sponsor, **Attach account** attaches the existing account that uses the address typed, and queues an invitation for it unless it was invited before; an address no account uses is refused, since a sponsor's first account is made with **Create account** on the **No account** view. Any account can be attached but an administrator's, a student's, an institution representative's or one that acts for another sponsor; a mentor's can. Every account attached to a sponsor has equal power on the Sponsor Dashboard. Under it, the posting switch says whether the sponsor's accounts can write posts in wp-admin, for an Administrator to publish, and **Turn posting off** or **Turn posting on** switches it; posting is switched here and nowhere else. A sponsor that is not Approved keeps its accounts, listed with **Remove**, and the view says no account can be attached to it and its posting cannot be switched. Unlike the list, the view prints the accounts' email addresses, because accounts are attached and removed by address. For an address that names no sponsor, it says "That link names no sponsor on this site." and that a sponsor is added on the **Sponsors** tab, with the way there.

**Offers and codes** lists every sponsor offer, the oldest hundred at most, with its sponsor, its kind, codes or one shared for everyone, its state, how many codes are available, claimed and void, the count it warns at and its last day, and, for support, who claimed from it: each person's name and address, the date and the code's last four characters, with **Void**, which lets the person claim again and keeps the code void for the count. A sponsor's primary offer is marked *(primary)*, and sponsors see counts only. Above the table, a sponsor that has an account and no offer has a button that creates its first offer, in draft, for the sponsor to complete and send for review from the Sponsor Dashboard.

**Interests** lists what sponsors said they would like to support from the Sponsor Dashboard, the last fifty, newest first, each with when it was sent, the sponsor, who sent it and what it said; each was mailed when it was sent, to the sponsor's assigned Administrator, or, for a sponsor with none, to the addresses in the **Interest mail** setting or to every Administrator.

**Reports** holds the **Reports** card: each sponsor's report, drafted, edited and marked final in an editor that opens in the card's place, a view of this tab, with the way back to the tab.

**Agreements** shows the standing state of every Approved sponsor's agreement, which is optional: a company's Sponsor Dashboard, offers and codes work without one. Each sponsor's block says what this site holds and offers the one thing that can be done next. An agreement accepted or recorded as on file has **Take it out of force**, with a note that is emailed to everybody at the company exactly as it is written. An agreement out of force is reinstated on the Administrator Dashboard, under **Out of force** in its **Sponsor Collaboration Agreements** card, as the block says, with the way to its own item there, or, while that page is missing, the sentence that says so in the link's place; that page lists the fifty oldest agreements out of force, so one past them has **Reinstate** here, under a sentence that says why. **Reinstate** asks before it acts, here and on the Administrator Dashboard alike: "Put this agreement back in force? Everybody at the company is emailed." Any other sponsor has **Record it as on file**, for a signed copy that predates this site: the link to it in the program's Drive folder, and the day it was signed if the copy says so. A document a company uploaded is not here: it waits on the **Waiting for review** tab, which this tab links.

**Handbook listing**, the last tab, holds the sponsors' listing for the handbook while the sponsor tiers are announced.

### Tools

The **Tools** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience - currently **Header notices**, **Emails**, **Need help?**, the
**Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
behind an *Open tool* button. Four of them, the **Mentor Status Checker**, the
**Student Duplicate Finder**, **Need help?** and **Emails**, keep their settings in a **Settings**
section on that screen, rather than on the Settings screen.
