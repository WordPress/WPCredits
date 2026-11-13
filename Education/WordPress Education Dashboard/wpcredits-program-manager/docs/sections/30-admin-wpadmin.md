## The plugin in wp-admin

Everything you manage in wp-admin is under one menu, **WPCredits Program**, given to the Administrator role. It holds **Overview**, a screen per audience, **Tools** with a screen per tool, and **Settings**.

Many buttons that change something lock at the first press and say what they are doing, so a second press does nothing.

### The words the screens use

The screens, the settings and this guide call each thing by one name.

| Name | What it is | Where you meet it |
| --- | --- | --- |
| **WPCredits Program** | The plugin, as the menu names it. Each screen under it has its own name alone: **Overview**, **Students**, **Tools**, **Settings** and so on. | The menu. This guide writes a place as `WPCredits Program > Settings`. |
| Audiences | The people the program is for and who run it: **Students**, **Mentors**, **Institutions**, **Sponsors** and **Administrators**. Each has its own screen. Administrators hold WordPress's Administrator role. | The menu, each audience's screen, the Settings tabs. |
| **Tools** | The parts run on their own: **Header notices**, **Emails**, **Need help?**, **Mentor Status Checker**, **Student Duplicate Finder** and **Track Builder**. | The **Tools** screen and the Overview's **Tools** card. |
| Landing page | Where an account goes when it logs in: **Mentor landing page**, **Student landing page**, **Institution landing page** and **Sponsor landing page**. The page's address is under each switch. | Settings, tabs **Students and mentors**, **Institutions**, **Sponsors**. |
| Remove and Leave | Each rule for someone who leaves has two answers, in this order: one starts with Remove, one with Leave. For example **Remove the Mentor role** and **Leave the role in place**. No account is deleted either way. | Settings, sections **When someone leaves**, **When an institution leaves the pipeline**, **When a sponsor is no longer Approved**. |

### Overview

{{image:admin-overview|The Overview screen: what waits for a decision, the syncs, the tools and the way to Settings.}}

**WPCredits Program** opens on the Overview. It reads and links: nothing is decided here. While Airtable is not connected, a warning at the top links to Settings.

#### The Overview's waiting list

**Waiting for a decision** lists each queue with something in it, in the Administrator Dashboard's order and with its counts. Past 200, the sponsor posts, offers and Collaboration Agreements read *200+*. With nothing waiting it says "Nothing is waiting for an Administrator right now."

Each line opens where its queue is listed:

| Lines | Open |
|---|---|
| Institution queues | **Institutions**: **Waiting for review**, **Semester reports** or **Accounts** |
| **Mentor applications waiting** | **Mentors**, tab **Waiting for review** |
| Sponsor queues | **Sponsors**: **Waiting for review**, **Reports** or **Offers and codes** |
| **Duplicated students** | **Student Duplicate Finder** |
| **Syncs need a look** | This screen's **Syncs** card |

**Students gone quiet** has no link. It shows only while **Daily list** under **Quiet students** is not **Off**. **Events waiting** shows only while **Sponsor tiers** is on.

**Decide on the Administrator Dashboard** opens that page. If the page is missing, the button's place says "The Administrator Dashboard page is missing. Re-activate the plugin to recreate it." These are decided on a screen instead:

- mentor applications, on **Mentors**;
- an institution or sponsor application past the 50 oldest open ones the dashboard lists;
- putting back or deleting a rejected or spam application.

Step by step: [[The Administrator Dashboard at a glance]]; [[Find an application or agreement past the fifty the dashboard lists]].

#### The Overview's Syncs card

**Syncs** has a row each for **Students**, **Mentors**, **Institutions** and **Outcome figures**. Each audience's name opens the tab its sync is run from.

- **Last run**: date and time, or *Never*.
- **Next run**: date and time, *Not scheduled*, or *Running now*.
- The **Students**, **Mentors** and **Institutions** rows show no errors here. Their last error is on the Administrator Dashboard's **Syncs and health** card, column **Last error**.

**Outcome figures** reads the homepage's three figures every Monday at 07:00 UTC, after the impact dashboard's build. Its name opens Settings, tab **Connection**. Its **Last run** is when the stored figures were read, so a held or failed read does not change it. After a failed read or a wait for the build, **Next run** shows a retry at the next whole hour. A line under **Last run** says when the last read was held, failed or waits, or the site is not connected. It also says when the homepage leaves a figure out (fewer than 30 answers or outcomes) or shows none (figures over 29 days old).

The weekly read never stores the first figures. Run `wp wpcredits outcome_figures --refresh --dry-run`, compare its counts with the impact dashboard's, then store them with `wp wpcredits outcome_figures --refresh`. `wp wpcredits outcome_figures` shows what is stored.

Step by step: [[Run a sync by hand and read its report]].

#### The Overview's Tools and Settings cards

**Tools** lists each tool, linked to its screen, with the **Status** its card shows on the **Tools** screen. A tool that cannot run says why.

**Settings** says whether Airtable is connected. **Open Settings** opens the Settings screen.

Step by step: [[Connect the site to Airtable for the first time]].

### The audience screens

One screen per audience under `WPCredits Program`. Each is a row of tabs, and a press made on a tab comes back to it.

| Screen | Its tabs | Opens on |
| --- | --- | --- |
| **Students** | **Accounts**, **Registrations**, **Interests**, **Sync** | **Accounts** |
| **Mentors** | **Waiting for review**, **Accounts**, **Engagement**, **Sync** | **Waiting for review** while an application waits, else **Accounts** |
| **Institutions** | **Waiting for review**, **Pipeline**, **Accounts**, **Semester reports**, **Agreements**, **Sync and storage** | **Waiting for review** |
| **Sponsors** | **Waiting for review**, **Sponsors**, **Accounts**, **Offers and codes**, **Interests**, **Reports**, **Agreements**, and **Handbook listing** while **Sponsor tiers** is on | **Waiting for review** |
| **Administrators** | **Accounts**, **Capabilities** | **Accounts** |

The Institutions and Sponsors screens are described under Institutions and Sponsors, below.

Where decisions are made:
- Most are made on the Administrator Dashboard.
- Mentor applications are decided on the Mentors screen.
- An institution or sponsor application past the 50 oldest the dashboard lists is decided on its screen.
- A rejected or spam institution or sponsor application is put back or deleted on its own screen.
- A sponsor's account comes from its approved application, or from **Create account** on the **No account** view of the Sponsors screen's **Accounts** tab.

Step by step: [[The Administrator Dashboard at a glance]]; [[Find an application or agreement past the fifty the dashboard lists]].

#### The Students screen's tabs

`WPCredits Program > Students`. Accounts are made by the sync from the Airtable Students Reports table.

| Tab | What it holds |
| --- | --- |
| **Accounts** | The **Invitations** card and the **Student accounts** list. |
| **Registrations** | The students who registered through the form on the site. |
| **Interests** | Students whose institution is not in the program yet. |
| **Sync** | The last sync's error, the **Airtable sync** card with **Sync students now** (or the progress and **Cancel sync**), and the **Last sync report**. |

While Airtable is not connected, **Accounts** and **Sync** say so.

Step by step: [[Run a sync by hand and read its report]]; [[A student the sync skipped, and a held invitation]].

#### The Student accounts list

Every Student account, a page at a time, like WordPress's own **Users** screen. Columns: **Student**, **Username**, **Status**, **Institution** and **Mentor**.

- **Status** shows a track by its name and some statuses in plain words, such as *Graduated*. A hyphen means none.
- **Search students** finds an account by name, username, email address or institution. It also takes a student's Students or Students Reports record ID.
- The views **All**, **Invited** and **Never invited** split the list by invitation, each with its count.
- The institution and status pickers narrow the list; press **Filter**. **Show all students** clears both.
- Each picker counts within the other's choice. A chosen option stays listed, even at 0.
- **Students by status:** shows a chip per status with its count. A chip narrows to that status and keeps the institution and view.
- Statuses run in the order of two lists under `Settings > Students and mentors`: those ticked under **Currently mentoring**, then under **Past students**, then the rest A to Z.
- A student with no status is in the list under all statuses and counted under none.
- The list opens by name, A to Z. **Student**, **Username** and **Institution** sort it; press again to reverse.
- Pickers, chips, search and sorts cover the whole list, not one page.
- **Screen Options** sets rows per page, 20 by default, and the columns. **Student** cannot be hidden.

Under each name: **Edit** opens the account, **View page** the Student Report Card, and **Send invite** or **Resend invite** emails that student at once.

Step by step: [[Find a student]]; [[Open a student's Student Report Card, and what you can change there]]; [[Invite a student, or several]].

#### Invitations from an accounts list

How the **Invitations** card and the invitation presses work on the Students and Mentors screens.

- The card's button invites everyone in the list never invited. On the Students screen it follows the chosen institution and status.
- It leaves out anyone whose invitation was held when an application made them a mentor, and says how many. Only that person's row sends it.
- While invitations go out, the count moves by itself. **Stop and cancel the remaining** stops the run; sent ones cannot be recalled.
- For several, tick rows, choose **Send invite** or **Resend invite** under **Bulk actions**, and press **Apply**.
- On WordPress 6.7 or later, a bulk action runs only on **Apply**. A search with rows ticked does nothing to them.
- **Send invite** queues a first invitation for each ticked account never invited. The card shows the progress.
- **Resend invite** queues a fresh one for each ticked account invited before. It replaces the earlier link and is not shown on the card.
- Nobody gets a second invitation within 15 minutes. Held invitations are left out too. The notice says how many.
- Every press comes back to the list as it was: view, search, filters, sort and page.

Step by step: [[Invite a student, or several]]; [[A held invitation, and an Airtable row that was refused]].

#### The Registrations tab

The **Student registrations** card: students who registered through the form on the site. The newest 50 are listed, with the total when there are more.

| View or outcome | What it means |
| --- | --- |
| **Written to Airtable** | A Students row was written. A link opens it. |
| **Already has an account** | The address has an account. A link opens it. |
| **Already registered** | Registered before. A link opens the row or the earlier registration. |
| **Waiting to be written** | Past the day's 40, held for an Administrator, or Airtable could not take it yet. It says which. |
| **Could not be written** | Not taken after 72 hours, or failed again on **Write it now**. It gives the reason. |
| **Spam** | Filed by the form's checks. |

Views: **All** (all but spam), **Waiting to be written**, **Could not be written**, **Spam**.

Rules:
- Each registration shows its date, name, email address, institution and outcome.
- Past 40 a day, the rest wait for the hourly job. A waiting one shows its tries and reason.
- Past 200 a day, the rest are held until **Write it now** is pressed.
- A registration also says what the row left out, and when the student's email was not sent.
- At a school with two or more tracks, the track request is in **Answers and consent** and Airtable's *Requested track* column. An Administrator assigns the track.
- **Answers and consent** holds the answers and the consent: when, from which address and browser.
- The site never changes or deletes a row it wrote. Correct it in Airtable.
- Administrators get no email when a student registers.

Presses, each back to its view:
- **Write it now**, on one that failed or is held: takes off the hold and tries once. A held one it does not settle then waits for the hourly job.
- **Put back**, on spam: queues it again and tries once.
- **Delete for good**, on spam: asks first. It cannot be brought back.

Step by step: [[A student registered]]; [[Open student registrations]].

#### The Interests tab

The **Interest from students** card: a student whose institution is not in the program yet names a representative there. Nothing is written to Airtable, and nothing is made from an interest.

The site never emails the representative. Write to them yourself, then press **Mark contacted**.

| View | What it holds |
| --- | --- |
| **New** | The tab opens here. No check held it; it may be marked as a repeat. The addresses in **Who hears of a new interest** (`Settings > Students and mentors`) were emailed, or every Administrator when it is empty. |
| **Held** | A check flagged it, such as one sent within seconds, one with several links, or one past 40 a day. Administrators were not emailed. |
| **Contacted** | You wrote to the representative. |
| **Closed** | Done. Deleted 365 days after closing. |
| **Spam** | Filed by the checks or by you. Deleted after 30 days. |

- The list is newest first, 50 a page, with **Newer** and **Older**. The heading counts the new ones.
- Each shows the student, the date, a reference, who last changed it, and what the checks noticed.
- Under it: **Institution**, **Department**, **Representative**, **Internships or practices**, **Anything else**, and **Consent** folded.
- **Mark contacted** works on *New* and *Held*; **Close** on *New*, *Held* and *Contacted*; **Mark as spam** on *New* and *Held*.
- **Put back to New** works on *Held*, *Spam* and *Closed*, and emails nobody. **Delete** works on *Spam* and *Closed*, asks first, and cannot be undone.
- A press on an interest another Administrator just moved changes nothing and says so.
- WordPress's **Export Personal Data** and **Erase Personal Data** find an interest by either address. An erasure deletes the whole interest.

Step by step: [[A student is interested in a school that is not in the program]]; [[Put the Student Interest Form on a page]].

#### The Mentors screen's tabs

`WPCredits Program > Mentors`. Accounts are made by the sync from the Airtable Mentors table, and when an application to mentor is activated.

| Tab | What it holds |
| --- | --- |
| **Waiting for review** | Applications to mentor, decided here. Applicants made mentors are under **Activated**. |
| **Accounts** | The **Invitations** card and the **Mentor accounts** list. |
| **Engagement** | How each mentor uses the site. Read only. |
| **Sync** | The sync card with **Sync mentors now**, its warnings, **Needs confirming** and the **Last sync report**. |

- **Sync** warns while a mentor made from an application has no Airtable row. **Write the Airtable row again** is under **Activated**.
- **Needs confirming** lists accounts matched to a mentor by username alone, each with **Confirm**.
- The applications are described under Applications to mentor, below.

Step by step: [[A mentor applied]]; [[A mentor's account waits under Needs confirming]]; [[Run a sync by hand and read its report]].

#### The Engagement tab

The **Mentor engagement** figures, then every mentor, with **Edit** and **View page** under each name. Columns: **Status**, **Students** (now and before), **Last visit**, **Last sign-in**, **Schedule**, **Notes**, **Calls** and **Group sessions**.

| View | Who it lists |
| --- | --- |
| **All** | Every mentor, active or not. |
| **Active** | Mentors Airtable holds as Active. |
| **With students now** | Active mentors with a current student. |
| **No sign-in on record** | Active mentors with no sign-in or visit recorded. |
| **Not seen in 30 days** | Active mentors last seen over 30 days ago. |
| **No program action** | No note, no saved schedule, no group session made and no call they canceled. |
| **Students, no weekly hours** | Active mentors with students and no weekly hours. |
| **Administrators** | Active Administrators who mentor. |

- A visit is the first signed-in page of a day. "Seen" is the later of the last visit and the last sign-in.
- The note under the list gives the day from which sign-ins are exact.
- Views but **All** and **Administrators** leave Administrators out, and the mentor figures count them apart.
- **No program action** includes mentors who never signed in, so it can exceed the card's figure.
- It opens with the most students first. **Mentor**, **Students**, **Last visit**, **Last sign-in**, **Notes** and **Calls** sort it.
- **Search mentors** takes a name, username, email address or Mentors record ID. **Screen Options**: 20 rows by default.
- `wp wpcredits mentor_engagement --summary` prints the figures; without `--summary`, one row per mentor. It changes nothing.

Step by step: [[Find mentors who have gone quiet or have no hours]].

#### The Mentor accounts list

Every Mentor account, a page at a time. Columns: **Mentor**, **Username**, **Students** (current), **Past** and **Status**: *Active*, or *Not in Airtable*.

- **Search mentors** takes a name, username, email address or Mentors record ID.
- Views: **All**, **Invited**, **Never invited** and **No students**, which lists mentors with no current student.
- It opens by name, A to Z. **Students** sorts fewest first, **Status** puts *Active* first. Press again to reverse.
- Sorts cover the whole list. **Screen Options**: 20 rows by default; **Mentor** cannot be hidden.
- There is no institution picker: the **Invitations** card invites every mentor never invited, but held ones.

Under each name: **Edit**, **View page** (the Mentor Report Card), and **Send invite** or **Resend invite**.

Step by step: [[Open a mentor's Mentor Report Card]]; [[A held invitation, and an Airtable row that was refused]].

#### The Administrators screen

`WPCredits Program > Administrators`. **Open the Administrator Dashboard** sits above the tabs while that page exists; while it is missing, the list says so.

- **Accounts**: the **Administrator accounts** list, every account with WordPress's Administrator role. Columns: **Name**, **Username**, **Can manage program**. A *No* there means re-activating the plugin grants the capability again.
- **Capabilities**: the **Program capabilities** the Administrator role is granted on activation.
- **Search administrators** takes a name, username or email address. **Name** and **Username** sort it.
- **Screen Options**: 20 rows by default. **Name** cannot be hidden; each row's **Edit** is under it.
- One view, **All**, and no bulk actions. The plugin never invites Administrators: manage their accounts on WordPress's **Users** screen.

### Applications to mentor

People apply to mentor on the site's `/become-a-mentor/` page, and you decide on `WPCredits Program > Mentors`, tab **Waiting for review**. The form writes nothing to Airtable.

Two switches under **Mentor applications** (`WPCredits Program > Settings`, tab **Students and mentors**), both off by default:

- **Applications from mentors** (**Take mentor applications through the form on this site**) opens the form. While it is off, the page says applications are closed.
- **Make mentors from applications** makes a mentor of a selected applicant whose course is done. While it is off, they wait under **Selected (course pending)**.

Step by step: [[From application to mentoring: the whole path]]; [[Open applications to mentor]].

#### The mentor application form

The site makes the page if none is at `/become-a-mentor/`: two paragraphs of introduction, then the **Mentor Application Form** block. A page already there is used as it is, so add the block to it.

- Edit the page to change the introduction.
- The sentence after sending is the block's **Thank-you sentence**, in its **Thank you** panel. Left empty, the site's own shows.
- While the countries or contribution teams list is empty, the public see nothing. An Administrator who opens the page reads why.

The questions:

- **Full name**, **Email** and **WordPress.org profile URL**. The profile takes a username, an @username or a profile address, and keeps the username.
- **Country**: type a few letters and pick one.
- **Are you a teacher from a WordPress Credits participating institution?** A Yes needs **Which institution do you teach at?**, picked from the Confirmed institutions. Test institutions show to Administrators only.
- **Languages in which you could mentor students** is required; **In which WordPress contribution areas do you have expertise?** may be left empty.
- **Available hours per week to mentor students**: 1 to 40, one decimal place.
- **Are you sponsored to contribute to WordPress?** A Yes needs **Which company sponsors you?**.
- **Would you like to be on the list of mentors looking for a sponsor?** Sponsors see the list once the person is an active mentor and not sponsored.
- **Compliance declaration** and **Privacy policy**: two boxes, both required. Nothing at all is stored without the second.
- **Anything else you'd like us to know?**: refused if WordPress would remove part of it, such as a tag. The form says how to keep every word.

A company or an institution given beside a No is not kept.

#### Checks on the mentor form

- One connection can send five submissions an hour, a form sent back for a missing answer included. The sixth is refused and not kept.
- Past 40 a day for the whole site, the rest are kept and held.
- Also held: one sent too soon after the page opened, one matching the site's comment disallowed list, and one with three or more links in its free text.
- Held, never merged: one whose address or username matches an application waiting or selected, or a mentor the site knows.
- A filled hidden field or a bad form token files it as spam. The sender sees the usual thank-you page.

The thank-you page shows the reference, such as *MAPP-2026-0007*. Every application not filed as spam gets the acknowledgement, with the link that confirms the address. The form sends at most 200 a day; past that, or if the email cannot be handed off, the page says none went.

The people under **Who is mailed**, else every Administrator, are emailed only about an application no check held.

#### The queue of mentor applications

`WPCredits Program > Mentors` opens on **Waiting for review** while an application waits, else on **Accounts**. The Administrator Dashboard counts waiting applications beside **Mentors** in its side menu and under **Waiting for you**.

| View | What it lists |
|---|---|
| **Waiting** | Every application not decided yet, held ones too |
| **Asked for more information** | Those you asked for more |
| **Second opinion** | Those marked for a colleague |
| **Selected (course pending)** | Those sent to the mentors' course |
| **Activated** | Those made mentors (see *Becoming a mentor*) |
| **Lapsed** | Course not shown in time, or withdrawn |

- Each view shows its count. Each but **Activated** lists only the oldest 50; the next appear as these are decided, and **Find by reference** reaches any other.
- **Recently decided**, under every view, lists the 50 newest declined or spam applications.
- An application shows its name and reference, its age or decision day, its marks, every answer, its **History** and its decisions.
- A selected one also shows what the Mentor Status Checker last found on the profile, and the day it lapses.
- **Find by reference** opens any application by its reference, whatever its view or age. One filed as spam is not found.

| Mark | What it means |
|---|---|
| **held** | The form's checks held it. Administrators were not emailed. |
| **possible duplicate** | An application waiting or selected has the same address or username. |
| **already a mentor** | A current or former mentor the site knows has this address or username. |
| **address not confirmed** | The applicant has not followed the link in the acknowledgement. |
| **acknowledgement not sent** | The applicant never got the link: filed as spam, past the day's limit, or not handed off. |

Step by step: [[A mentor applied]]; [[Bring back a declined, lapsed or spam application]].

#### Decisions on a mentor application

| Decision | Offered on | Sent to the applicant | Then |
|---|---|---|---|
| **Select** | Not decided yet, lapsed, declined | The selection email, with the course link | **Selected (course pending)** |
| **Ask for more information** | Not decided yet | The request for more information | Stays in **Waiting** |
| **Second opinion** | Not decided yet | Nothing | Stays in **Waiting** |
| **Decline** | Not decided yet | The decline email | **Recently decided** |
| **Mark as spam** | Not decided yet | Nothing | **Recently decided** |
| **Withdraw** | Selected | Nothing | **Lapsed** |
| **Put back in the queue** | Filed as spam | The acknowledgement, if it never had one | **Waiting** |
| **Send the acknowledgement** | Not decided yet, **acknowledgement not sent** | The acknowledgement | Stays |
| **Send the confirmation link again** | Not decided yet, **address not confirmed** | The acknowledgement again | Stays |
| **The course is completed** | Selected, while **Make mentors from applications** is on | The invitation, unless held | **Activated** |
| **Write the Airtable row again** | Activated, row not written | Nothing | Stays |

- **Select** is refused while **Mentors' course invitation link** is empty.
- A note stays on the site and is never sent, up to 2,000 characters. **Second opinion** and **Withdraw** need one of at least 10.
- **Select**, **Decline**, **Mark as spam**, **Withdraw**, **The course is completed**, **Send the acknowledgement** and **Send the confirmation link again** ask before they act.
- If another Administrator decided it first, **Select**, **Ask for more information** and **Decline** say who, how and when, and send nothing.
- If an email cannot be handed off, nothing changes. The **Log** tab of `Tools > Emails` says why.

Step by step: [[A mentor applied]]; [[The course is completed: make the applicant a mentor]].

#### The confirmation link

**Select**, **Ask for more information** and **Decline** are refused until the applicant follows the link in the acknowledgement. **Second opinion** and **Mark as spam** still work.

- **Send the confirmation link again** is the first button on the application, and the refusal notice carries it too. The first link keeps working.
- It counts against the day's 200 acknowledgements. **Send the acknowledgement** does not.
- Close an application whose address is never confirmed with **Mark as spam**.

Step by step: [[The applicant's address is not confirmed]].

#### Lapsing and deleting mentor applications

A daily run does both.

- A selected application moves to **Lapsed** once more days than **Selected applications lapse after (days)** have passed since it was selected, whether or not the course shows. It is 90 by default, 14 to 365, under **Mentor applications** on the **Students and mentors** tab.
- Spam goes after **Spam kept for (days)**, 30 by default.
- Declined, lapsed and held applications go after **Rejected kept for (days)**, 365 by default.
- Activated ones go after **Approved kept for (days)**. The default, 0, keeps them. One waiting for its Airtable row is kept.
- Other applications in **Waiting** are never deleted.

The three kept-for settings are under **Applications** on the **Advanced** tab of Settings.

Step by step: [[Change how long applications and files are kept]].

#### Mentor application figures

The counts are public at `/wp-json/wpcpm/v1/mentor-applications`, with nothing about any person.

- Per month: applications received, selected, activated, declined and lapsed. Today: how many are open and how many selected.
- Each application counts once per figure in a month. Do not add the months up.
- Spam is not counted, a withdrawal is a lapse, and one filed as already a mentor is not counted as activated.

#### Mentor application emails

Edited in `Tools > Emails`, area **Mentor applications**.

| Email | Who gets it | When |
|---|---|---|
| **Mentor application received, to the applicant** | The applicant | They apply |
| **Selected to mentor: the mentors' course** | The applicant | You press **Select** |
| **More information asked of a mentor applicant** | The applicant | You press **Ask for more information** |
| **Mentor application declined** | The applicant | You press **Decline** |
| **New mentor application, to Administrators** | **Who is mailed**, else every Administrator | An application passes every check |

The new mentor's invitation is **Invitation: mentor**, area **Invitations**.

Step by step: [[Change the words of an email and test it]].

#### Becoming a mentor

With **Make mentors from applications** on, a selected applicant becomes a mentor when:

- the Mentor Status Checker finds the course on their WordPress.org profile when it promotes (the daily check promotes only with **Let the daily check also promote mentors** ticked); or
- you press **The course is completed**.

The step, in order:

1. The account: the one with their address, else named after their WordPress.org username, else holding their profile. A found account that holds its own Mentors row is used. If it holds none, and the one Mentors row with their address is linked to another account, that one. With none, a new account named after the username.
2. The account gets the Mentor role. No role is taken away, and an Administrator's roles are never touched.
3. The application moves to **Activated**.
4. The invitation, which carries the welcome, is queued whatever **Invitation emails** says. The queue sends ten at a time. The Mentor Status Checker's Slack message names the mentor.
5. The Airtable row. The mentor's own Mentors row, or the one row with their address, has its Status set to the value of **Mentor status to sync** (tab **Students and mentors**, section **Who is on the program**; *Active* by default). Else a new row is written, with that Status and the answers the table has columns for.

- If the account cannot be made, the application stays selected, with the reason.
- No invitation goes to an Administrator. It is held when the account was found by username alone, or another account also answered for the applicant. The **Invitations** card and **Send invite** on ticked accounts skip it.
- No row is written while the value of **Mentor status to sync** is blank, when two rows hold the address, or when the row is linked to another account.
- A country, language or contribution area the base lacks is left out by name. Without the token's `schema.bases:read` scope, the row holds the name, profile, email and Status only.
- Nothing else in Airtable is changed or deleted, and the site never updates the row again.
- An applicant who is an active mentor already is only filed under **Activated**. Nothing is sent or written.

If Airtable refuses the row, the mentor is made all the same. The application shows Airtable's words and **Write the Airtable row again**. For a returning mentor it sets their own row, and searches by address only if Airtable says that row is gone; it never makes a second row. The mentors sync leaves the mentor alone meanwhile. The **Sync** tab counts mentors waiting for their row.

**Activated** shows 50: those waiting for their row first, oldest first, then the newest mentors. Each has three lines:

| Line | What it says |
|---|---|
| Account | The account, linked to its profile, or that it is gone |
| Invitation | Since the activation: in the queue, sent, handed over with no word, refused by the mail host, held, or none sent |
| Airtable | Written, set back, linked, or refused with Airtable's words; and what was left out |

Step by step: [[The course is completed: make the applicant a mentor]]; [[The Mentor Status Checker: check, then promote]]; [[A held invitation, and an Airtable row that was refused]].

#### Switching it on

**Make mentors from applications** ships off. The person who runs the site switches it on in this order:

1. In `Tools > Emails`, edit **Invitation: mentor**. Replace the bracketed line with each course's invitation link and the mentor resource folder's link. Save. It is the email for every new mentor: an activation's, one sent from the Mentors screen, and one the mentors sync queues.
2. A saved edit is sent instead of the shipped text. If the email was edited before, check your edit holds the welcome.
3. Set **Contact address in emails**: the welcome asks mentors to reply, and a reply goes to that address.
4. In Airtable, make sure no automation fires on the check row.
5. Run `wp wpcredits mentor_airtable_row --check --email=<the test mailbox's address>` once. It writes a test row to the Mentors table, reads it back, deletes it, and lists the columns the base kept.
6. If it reports a row it could not delete, delete it in Airtable by hand. If it says the base would not take the whole row, put that right first.
7. Switch **Make mentors from applications** on. In Airtable, switch off the automation that emails the welcome.

The check changes nothing on the site. `wp wpcredits mentor_airtable_row <reference>` prints the row an application would write and writes nothing. Run it on a test application: it prints the applicant's answers.

Step by step: [[Set the contact address the emails name]]; [[Change the words of an email and test it]].

### Institutions

The program's schools, at `WPCredits Program > Institutions`, in six tabs: **Waiting for review**, **Pipeline**, **Accounts**, **Semester reports**, **Agreements** and **Sync and storage**. The screen opens on **Waiting for review**.

- The result of a press is said above the tabs.
- While Airtable is not connected, a warning above the tabs says so, with **Open settings**.
- The menu entry's bubble counts the applications, signed agreements and requests waiting. At 200 or more it reads *200+*.

#### The Waiting for review tab

The queue: applications, signed agreements and requests from institutions in one list, oldest first. It is read here and decided on the Administrator Dashboard. The list shows the oldest 50.

Each row gives its kind: **Application**, **Signed agreement**, **Mentor request**, **Details to correct** or **Request**. It says how long it has waited, and ends with **Open on the Administrator Dashboard**.

A line gives the row's country and the Administrator it is assigned to, for information, or says nobody is assigned. A row with no country has no such line.

| Mark | What it means |
|---|---|
| *overdue* | An application or agreement waited longer than **Agreement review** (`WPCredits Program > Settings`, tab **Institutions**; 3 days by default). A request waited over 14 days. |
| *held* | The anti-spam checks held the application. |
| *possible duplicate* | Another application in the queue names the same institution or address. Nothing is merged. |
| *approval stopped partway* | Approving stopped after the Airtable record was made. Press **Approve** again to finish. |

- An application's row has **Open this application** and its state.
- A request's row says what it asks and who raised it, and names the student when it is about one.
- A signed agreement's row shows its checklist and **Download the signed agreement**.
- While the Administrator Dashboard page is missing, the list says so and no row links there.

Step by step: [[A school applied]]; [[A signed Collaboration Agreement arrived]]; [[A school asks to correct its details]]; [[A school's other requests (a student to add, a mentor wanted)]].

#### One application

**Open this application** shows one application, with **Back to the queue**:
- its state, when it arrived, and whether the address is confirmed;
- **What the checks made of it**, or **Why this application is held**;
- every answer under its Airtable column, and the consent: the sentence, when, and the policy's address;
- five answers kept on this site only: the contact's role, the students' languages, who will sign, their title and the school's page about WordPress Credits;
- **What Airtable already has**: records with the same name or address. Approving adopts the first instead of creating one.

**Where it is decided** says where the decisions are. The dashboard's **Institution applications** card lists the 50 oldest open applications. One past them is decided here. A rejected, spam or approved one has its record-keeping here.

- **Approve** creates the Airtable record and the account, and emails a password-set link. It is refused until the applicant follows the link in the acknowledgement.
- **Approve** is also refused when the contact's address already has an account on the site.
- **Send the confirmation link again**, while the address is unconfirmed. The site sends at most 200 acknowledgements a day.
- **Send this question**: 10 to 2,000 characters, emailed with your address to reply to. If it cannot be sent, it stays in its box.
- **Reject**: the applicant is told, with no reason. Your reason stays on the site. With no usable address, or if the mail host refuses, the notice says nothing was sent.
- **Reject as spam**: nothing is sent.
- **Put back in the queue**, on a rejected or spam one. A spam one never acknowledged is sent the acknowledgement, once.
- **Delete for good** keeps only the reference and the date. It cannot be undone.

While *approval stopped partway* shows, **Reject**, **Reject as spam** and **Put back in the queue** are refused.

Step by step: [[The application's address is not confirmed]]; [[Finish an approval that stopped halfway]]; [[Find an application or agreement past the fifty the dashboard lists]].

#### The Pipeline tab

Every institution record by stage, as the last institutions sync read it. Each row: **Institution** (it opens the Institution Dashboard), **Country**, **City**, **Contact** (*email on record* or *no email*), **Consent**, **Created** and **Agreement**.

- *N students now* beside a name: students enrolled now, with or without a mentor. Each stage's heading adds *N with students now*.
- *no area* beside a country: no area of responsibility covers it. A line lists those countries.
- SPAM, Not Moving Forward, Revisit Later and no stage are not counted. They are folded under **Not counted above**.
- **Confirmed with no agreement recorded** narrows the list. **Show every stage** brings the rest back.
- **Institutions with no live member** counts those nobody can act for on this site.
- **Find an institution** finds any record by part of its name. **Open its page** opens it.
- The **Consent** card counts the records with no consent tick, from before and since the form asked for it.

While the Institution Dashboard page is missing, names are not links and **Find an institution** is not shown.

Step by step: [[Open a school's Institution Dashboard]]; [[Close a "no area" gap]]; [[Where a school stands]].

#### The Accounts tab

The **Invitations** card and the **Institution accounts** list.

- **Invitations** invites every institution account never invited. An account that also mentors and whose invitation is held is left out, and counted.
- A warning names any account locked out of roster changes today. The lock lifts by itself tomorrow.
- **Search institutions** finds a name, username, email address or institution.
- Views: **All**, **Invited**, **Never invited**, and **No account**.
- **Screen Options**: rows a page (20 by default) and columns. **Name** cannot be hidden.
- Columns: **Name**, **Username**, **Institution**, **Member since** and **Status** (**Active** or **Membership ended**, with *locked today*). Each sorts the whole list; the list opens by name, A to Z.

Under each name: **Edit**, **View page** (the Institution Dashboard as that school), **Send invite** or **Resend invite**, and **Manage members**. A new invitation replaces the link in any earlier one.

To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**. Nobody gets a second invitation within 15 minutes.

#### The No account view for institutions

Confirmed institutions with no account yet, ready ones first. **Contact** says whether Airtable holds an address. **Account** says **Ready**, or why not.

- **Create account**, under a ready name or under **Bulk actions**, uses the Contact Email in Airtable and queues an invitation. It cannot be recalled once sent.
- One press creates at most 25 accounts.
- While any Confirmed institution here has no agreement recorded, nothing is created in bulk.
- **Manage members** is under every row here, to add a person by name and address.
- Under the list, the view says whether the sync creates these accounts too: **Create accounts automatically**, tab **Institutions** in Settings.

Step by step: [[Give a school its first account]].

#### Manage members

One institution's people, under **Representatives and tutors**, with **Back to the accounts**.

- **Remove** ends a member's access and keeps the account. Nobody is emailed. The invitations they sent that are still pending are canceled.
- After the last member goes, every pending invitation is canceled and **Institution has no members left, to Administrators** is sent. It goes to **Who is emailed about institutions** (tab **Institutions** in Settings), or to every Administrator when that is empty.
- **Former members** have **Re-add**. For an account whose membership ended, **Manage members** opens the institution it left.
- **Add an account**: **Name**, **Email address**, then **Add account**. It makes an account with a random password and queues an invitation.
- An address with an account is taken only from a former member of this institution or a mentor. Any other is refused, naming its username. A current member is told it is already on the list.
- An account invited before is not sent another invitation.
- **Upload a signed agreement on the institution's behalf**, for a copy sent by email. It joins the queue, and the school is emailed. While one is waiting for review, **Download the copy waiting for review** and a withdraw control replace it.

Pending invitations are on the Institution Dashboard. An invitation from someone no longer a member, or no longer an Administrator, cannot be accepted.

#### The Semester reports tab

All drafts, then the 60 most recently edited approved reports. The heading counts the drafts.

- Each row: **Institution** (it opens the report), **Semester**, **State**, **Drafted** (*by the site* or *by an Administrator*) and **Last edited**.
- **Ask the students** writes to the semester's students who gave feedback and have not said if it may be used. Nobody is asked twice in 30 days.
- **Due for drafting**: the semesters the nightly job would draft, each with **Draft now**.
- **Draft any semester**: choose an **Institution** and a semester, then **Draft now**.
- **Report log**: the last 20 things that happened to a report.

Step by step: [[A semester report: draft, review, approve, reopen]].

#### The Agreements tab

- **Agreements on file**: Confirmed institutions with no agreement recorded. Each opens the school's **Collaboration Agreement** panel, with **Record an agreement the program already holds**.
- **Agreements in force**: each standing agreement, **Accepted on the site** or **Recorded on file**, and the day.
- **Revoked agreements**: each school with a revoked agreement and nothing accepted or waiting for review, also one that has since started a new agreement or had one returned: the day it was revoked, the Administrator's note and **Reinstate**.
- **Agreement discrepancies**: where the site and Airtable's Agreement Status disagree. Each is locked until they agree.
- **Agreement template**: the site's copy of the agreement, its version, the date it was copied from the program's Doc, and which institutions signed which version.

**Revoke this agreement**, under an agreement in force:
- It needs **Why it is revoked, in your own words**: 20 to 2,000 characters, emailed to every member.
- Members keep their accounts but reach only the agreement panel. Pending invitations are canceled.
- Airtable is updated first. If that fails, nothing changes.
- The stage stays. If the partnership ended, change it in Airtable.
- A refused note comes back to its box.
- **Reinstate**, under **Revoked agreements**, puts it back.

**Reinstate**, under **Revoked agreements**:
- It asks "Reinstate the agreement with {school}? It is back in force at once, and everybody at the institution is emailed."
- Airtable is updated first. The school's people get "Your agreement is in force again".
- The press comes back to the **Agreements** tab. The Administrator Dashboard's **Collaboration Agreements** card still offers it while a signed agreement waits for review.
- It is refused when an accepted agreement already stands, and for any but the most recently revoked one.
- A school at a stage outside the active ones is listed without the button, with the line "At the stage "{stage}", which is outside the pipeline. Move it to an active stage in Airtable first: reinstated now, the next institutions sync would close it again." A school with no stage keeps the button.

Step by step: [[Record an agreement the program already holds]]; [[Take an agreement out of force, or put it back]].

#### The Sync and storage tab

- The last sync's error, if it ended in one.
- **Airtable sync**: when the last sync finished, and **Sync institutions now**. While one runs, its progress and **Cancel sync**.
- **Reconciliation**: where the Students table and Students Reports disagree, from the last students sync. A Students row with no institution has **Link** where linking is safe, and otherwise says why not. SPAM records are not offered.
- **Storage**: how signed agreements are kept, and whether the host refuses strangers. **Run probe** checks again.

Step by step: [[Run a sync by hand and read its report]]; [[A student with no institution]]; [[Check that agreement files are safe on the host]].

#### Test institutions

An institution to try the tools on. Its name decides it: there is no box.

- The trimmed name starts with TEST in capitals, then nothing or a character that is not a letter or digit.
- *TEST: Example* and *TEST (Demo)* are tests. *Test College*, *Contest Labs*, *TESTAMENT* and *TEST2 Labs* are not.
- Renaming it in Airtable takes effect at the next institutions sync.
- Keep it below Confirmed in Airtable, so no public page lists it.

It is listed wherever a real one is, marked **Test**, or *(Test)* in a picker or an email. It is left out of every count, total and figure, except the **Invitations** button and a running sync's progress. So a heading can count fewer than its rows, even 0.

Everything else works as for a real one, except:
- Accepting its agreement never moves its Airtable stage to Confirmed, so public pages never list it.
- The area Administrator is not emailed when the sync assigns it.
- Its students get no reminder when they go quiet.
- The Slack message about a newly Confirmed institution leaves it out.

When testing ends, remove in Airtable the test students' rows, their report rows and the institution's row. Remove the test representatives' accounts on the site. Its agreement, requests and reports stay uncounted.

Step by step: [[Set up a test institution]].

### Sponsors

`WPCredits Program > Sponsors` is where you look after the sponsors, which are this site's own records. Each press comes back here, with the outcome above the tabs.

#### The Sponsors screen's tabs

The screen has seven tabs, and an eighth while **Sponsor tiers** is on in Settings. It opens on **Waiting for review**.

| Tab | What it holds |
|---|---|
| **Waiting for review** | What sponsors sent that waits for a decision |
| **Sponsors** | Every sponsor, its Edit form, and the form to add one |
| **Accounts** | The people who act for sponsors, and their invitations |
| **Offers and codes** | Every offer, its codes and who claimed them |
| **Interests** | What sponsors said they would like to support |
| **Reports** | Each sponsor's yearly reports |
| **Agreements** | Each *Approved* sponsor's agreement |
| **Handbook listing** | The partners' listing, only while **Sponsor tiers** is on |

The menu entry's bubble counts the applications, signed agreements, posts and offers waiting. While **Sponsor tiers** is on, it counts the events waiting too. At 200 or more it reads *200+*.

#### Waiting for review on the Sponsors screen

A card for each kind of item waiting, oldest first. You read them here and decide them on the Administrator Dashboard.

| Card | Each item shows |
|---|---|
| **Sponsor applications** | Name, how long it waited, website, contact, marks |
| **Sponsor posts to review** | Sponsor, writer, when, and **Preview** |
| **Sponsor offers to review** | A new offer or changes to a live one, whose, who sent it, when |
| **Events** | Sponsor, planner, when, and the event's time in your time zone. Only while **Sponsor tiers** is on |
| **Signed agreements** | Uploader, date, size, what to check, what the scan noticed, **Download the signed agreement** |

- Each open item has **Open on the Administrator Dashboard**, which opens its own item there.
- Each card but **Events** lists the 50 oldest, as the dashboard does. A line says so when more wait.
- While the Administrator Dashboard page is missing, the tab says so and links nowhere.

| Mark on an application | What it means |
|---|---|
| *held* | The anti-spam checks held it |
| *possible duplicate* | Another one in the queue names the same company or address |
| *already on the site* | A sponsor with this name or website exists |
| *already an account* | The contact address belongs to an account here |
| *approval half done* | An earlier **Approve** stopped. Press **Approve** again to finish |

**Recently decided** lists the 50 latest decided applications, newest first, with **Open this application**.

Step by step: [[A company applied to sponsor]]; [[A sponsor sent a post for review]]; [[A sponsor sent an offer for review]]; [[A sponsor planned an event]]; [[A sponsor uploaded its signed agreement]].

#### An opened sponsor application

**Open this application** shows the application's state, anti-spam findings, answers, consent, logo files and **What the site already has**, with **Back to the queue**.

Under **Where it is decided**:

| Application | What you can do here |
|---|---|
| Open, among the dashboard's 50 oldest | Follow the link to its item on the dashboard |
| Open, past the 50 oldest | **Approve**, **Send this question**, **Reject**, **Reject as spam**; **Put back in the queue** if held or waiting on the applicant |
| Rejected or marked as spam | **Put back in the queue**, **Delete for good** |
| Approved | **Delete for good** |

On an application marked *approval half done*, **Reject**, **Reject as spam** and **Put back in the queue** are refused until **Approve** finishes it.

**Delete for good** keeps only the reference and the date, and cannot be undone. The logo files go too, unless the application was approved.

Step by step: [[A company applied to sponsor]].

#### The sponsor list and the Edit form

The tab **Sponsors** lists every sponsor. The name opens its Sponsor Dashboard; a test sponsor is marked *(test sponsor, not listed publicly)*. The columns are **Status**, **Tier**, **Product**, **Program contact**, **Accounts** (how many) and **Logo** (*On the site* or *None*). Each row ends with **Edit** and **Manage accounts**.

**Edit** opens the sponsor's form, with **Save**, **Cancel** and **Back to the sponsors**.

- The box **Test sponsor: not listed publicly**, right after **Status** on both forms: the sponsor is left out of the Partners page, the logo strip, the handbook listing and the public feed. No student or mentor sees its offers, posts or events. Its own accounts still see its Sponsor Dashboard.
- A refused field is named at the field, and your typing is kept. A bad website reads "Enter a web address such as example.com."
- If someone saved the sponsor after you opened the form, nothing is saved. The form says when, and keeps your typing.

**Add a sponsor**, under the table, is for a company that never applied. Its **Status** box starts at *In review*; nothing else happens, and its Edit form opens. Once it is *Approved*, make its account with **Create account** on the **No account** view.

While the box **Moving sponsors onto the site** shows, the move runs by itself at most once a minute. **Run again** runs it now.

Step by step: [[Add a company that never applied]]; [[Pause or stop a sponsorship]].

#### Sponsor accounts and invitations

The tab **Accounts** holds the **Invitations** card and the **Sponsor accounts** list.

- A warning names any sponsor account locked out of changes for today. The lock lifts by itself the next day.
- **Invitations** invites every account never invited. It leaves out, and counts, one whose invitation is held until an Administrator confirms it.
- **Search sponsor accounts** finds a name, username, email address or sponsor. The list itself shows no address.
- The views are **All**, **Invited**, **Never invited** and **No account**, each with a count.
- **Sponsor** adds the sponsor's status in parentheses when it is not *Approved*.
- **Status** is *Active* or *Membership ended*, with *locked today* added.
- The list opens by **Name**, A to Z. Every column sorts the whole list; a second press reverses it.
- **Screen Options** sets the rows a page, 20 by default, and the columns. **Name** cannot be hidden.

Under each name: **Edit** (the account in wp-admin), **View page** (its Sponsor Dashboard, while the membership lasts), **Send invite** or **Resend invite** (at once) and **Manage accounts**.

For several, tick them and use **Send invite** or **Resend invite** under **Bulk actions**. Nobody gets a second invitation within 15 minutes, and the notice says how many were left out.

Step by step: [[Give a sponsor an account, and manage its people]].

#### The No account view for sponsors

The view **No account** lists every *Approved* sponsor that no account acts for yet. Ready ones come first, then by name. An approved application makes its sponsor's account itself; this view is for any other *Approved* sponsor.

- **Sponsor**: the name over its key.
- **Contact**: the contact address, partly hidden, or *no email*.
- **Account**: **Ready**, or ready with an existing account to attach, or why not: no contact address (add one on the Edit form), or an administrator's address.

**Create account**, under a ready sponsor or under **Bulk actions**, does this:

- It makes the account at the contact address, or attaches the account already using it.
- It queues an invitation, unless the account was invited before. An invitation cannot be recalled.
- It makes the sponsor's first offer, in draft, if it has none yet, and writes a line in the sponsor's log.
- One press makes at most 10 accounts. The notice names any sponsor it could not serve, and why.

Step by step: [[Give a sponsor an account, and manage its people]].

#### One sponsor's accounts

**Manage accounts** shows one sponsor's accounts, under its name and **Back to the accounts**. The Sponsor Dashboard's **People** card leads here too.

- Each account shows its name and email address. **Remove** asks first, then ends the membership and the right to write posts. The account stays.
- **Attach account** attaches the existing account at the address you type, and queues its invitation if it never had one. An address no account uses is refused: a first account is made with **Create account** on **No account**.
- An administrator's, a student's, an institution's or another sponsor's account is refused, as is one already attached here. A mentor's can be attached.
- **Turn posting on** or **Turn posting off** lets the accounts write posts in wp-admin, or stops them. It is switched only here.
- For a sponsor that is not *Approved*, you can only remove accounts.

Step by step: [[Give a sponsor an account, and manage its people]].

#### Offers and codes on the Sponsors screen

Every sponsor offer, the oldest 100 at most, for support. Sponsors see counts only.

- The columns: **Sponsor**, **Offer** (the sponsor's primary offer is marked **(primary)**), **Kind**, **State**, **Available**, **Claimed**, **Void**, **Warns at** and **Last day**.
- **Claimed by**: each person's name, address, date and the code's last four characters, with **Void**.
- **Void** asks first, then lets the person claim again. The code stays void in the count.
- A sponsor with an account and no offer gets **Create the first offer for** and its name. It makes a draft.

Step by step: [[Void a code claim]].

#### Interests on the Sponsors screen

The last 50 things sponsors said they would like to support, newest first. Each was emailed when sent: to the sponsor's assigned Administrator, else to **Who is mailed** (`WPCredits Program > Settings`, tab **Sponsors**), else to every Administrator.

Step by step: [[A sponsor would like to sponsor a mentor]].

#### Reports on the Sponsors screen

The **Reports** card lists each *Approved* sponsor's yearly reports, with their state and **Open**, which opens the editor. **Draft a report** drafts one for the term's last day you choose, where the tier carries a report.

Step by step: [[A sponsor's annual report]].

#### Agreements on the Sponsors screen

Each *Approved* sponsor's agreement, with the next step. An agreement is optional: the Sponsor Dashboard and offers work without one.

| The site holds | You can |
|---|---|
| An accepted agreement, or one on file | **Take it out of force**, with a note of 20 to 2,000 characters emailed to everybody at the company |
| One out of force | Reinstate it on the Administrator Dashboard, under **Out of force** in its **Sponsor Collaboration Agreements** card. Past the dashboard's 50 oldest, **Reinstate** is here |
| Anything else | **Record it as on file**: the Drive link to the signed copy, and the signing day if known |

**Reinstate** asks first, since everybody at the company is emailed. An uploaded document waits on **Waiting for review**.

Step by step: [[A sponsor's agreement: record, take out of force, put back]].

#### Handbook listing on the Sponsors screen

**Listing for the handbook** holds the partners as the Partners page lists them, as plain HTML, with **Copy**. The tab shows only while **Sponsor tiers** is on.

### Tools

`WPCredits Program > Tools` has a card for each tool, with **Open tool**: **Header notices**, **Emails**, **Need help?**, **Mentor Status Checker**, **Student Duplicate Finder** and **Track Builder**.

Four keep their settings in a **Settings** section on their own screen, not on the Settings screen. **Need help?**, **Mentor Status Checker** and **Student Duplicate Finder** have it at the top; **Emails** has it on its tab **Signature and footer**.
