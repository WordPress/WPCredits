## Running it day to day

### The sync

Three syncs bring the site in line with Airtable: students, mentors and institutions.

#### When the syncs run

- While **Automatic sync** is ticked (`WPCredits Program > Settings`, tab **Students and mentors**), all three run every three hours, half an hour apart: students, mentors, institutions. Default: on.
- WordPress starts each run at or after its time, so two can still fall close together.
- A run still going when the next is due is left to finish. A stalled run is started again.
- By hand: **Sync students now** and **Sync mentors now** on the Students and Mentors screens (tab **Sync**), **Sync institutions now** on the Institutions screen (tab **Sync and storage**).
- One run at a time: a press during a run starts nothing.
- A brief refusal from Airtable makes the run wait, then carry on.

#### What each sync reads and makes

| Sync | Reads in Airtable | Makes on the site |
|---|---|---|
| Students | Students Reports, mentors' contact details, the Students table | Student accounts and each student's program details |
| Mentors | Mentors at **Mentor status to sync**, student reports, the Students table | Mentor accounts, each mentor's students, the public list of mentors |
| Institutions | The Countries and Institutions tables | The site's copy of each institution, a first account while **Create accounts automatically** is ticked (tab **Institutions**), and removes the members of a school that left the pipeline |

- A student in **Past students** keeps an existing account but is never given a new one.
- An institution's first account is made from its Contact Email, only for a *Confirmed* school whose agreement is recorded and that never had a member. An address already on an account is left alone.
- A school that leaves the stages in **Institution pipeline stages** (tab **Advanced**) loses its open agreement. By default its people lose their access; **Its people** (tab **Institutions**) decides.

#### How an account is matched

- Students: by the Airtable record the sync stored, then by email address, never by username.
- A student's address that belongs to a mentor's account, an institution member's account, or an account with a role other than Student or Subscriber is a conflict. The account is left as it is.
- Mentors: by the stored record, then by email address, then by username.
- A mentor's account found by username alone is not linked. It waits under **Needs confirming** on the Mentors screen's **Sync** tab. **Needs confirming** is for the sync only. Making a mentor from an application links an account found by username at once, and holds its invitation.
- **Confirm** links it and gives it the Mentor role; an Administrator's account keeps its roles. With **Invitation emails** on, the invitation is queued, unless the account was an active mentor already, is an Administrator's, or its invitation is held.
- A run that passes the count check removes a waiting match whose mentor has left the base.
- An account linked to another record is a conflict. A mentor with no WordPress.org username or no valid address is skipped.

Step by step: [[A mentor's account waits under Needs confirming]]; [[A student the sync skipped, and a held invitation]].

#### Accounts of people who leave

A linked account the run did not read has left. **When someone leaves** (tab **Students and mentors**) decides whether the role is removed. The default removes it.

- A mentor who left loses their list of students with either choice.
- A student in **Past students** counts as having left.
- An Administrator's roles are never touched. An account left with no role becomes a *Subscriber*.
- No sync ever deletes an account.
- A mentor made from an application keeps the role while their Mentors row is not written, or was written after the run began. The **Sync** tab says how many have no Airtable row yet.

#### The count check

Before the mentors or students sync takes a role, it compares the records it read with the accounts linked to a record.

- It stops when it read none, or under half as many. It never stops while nothing is linked.
- A stopped run keeps every account, and the automatic runs wait for you.
- **Run anyway** is on the **Sync** tab while a run is stopped, and goes on for that run only.

Step by step: [[A sync stopped before taking roles]].

#### The Last sync report

The **Last sync report** card on the Students and Mentors screens' **Sync** tab shows the last finished run: counts, then **Warnings**, which name each person skipped and why. It keeps 100 warnings.

| Line | What it counts |
|---|---|
| **Existing accounts linked** | Accounts linked to a record for the first time |
| **Accounts refreshed** | Accounts already linked, brought up to date |
| **Invitations sent to existing accounts** | Mentors only: welcomes queued for existing accounts |
| **Students skipped (missing data)**, **Mentors skipped (missing data)** | Students: no valid address, or an account that could not be made. Mentors: also no WordPress.org username |
| **Account conflicts** | An address or account that belongs to someone else |
| **Students assigned to a non-active mentor** | Mentors only: reports linked to a mentor the run did not read |

Step by step: [[Run a sync by hand and read its report]].

#### The welcome for an existing account

With **Invitation emails** on, the mentors sync queues the invitation, which carries the welcome, for an existing account it makes an active mentor.

- Each mentor gets it once each time they become active. An Administrator never does.
- An account whose invitation is held is named in a warning and left as it is.
- A run that would welcome more than five sends none. It holds each and names them in the first warning. **Send invite** on the account's row, Mentors screen, tab **Accounts**, sends it.

#### The public list of mentors

The mentors sync keeps a list of active mentors for a mentor directory: name, WordPress.org profile, country, languages and whether sponsored. No address.

- Only a run that read the whole Mentors table and named every country and language replaces it. A linked table it could not read is named under **Warnings**.
- The list is as fresh as the last finished mentors sync.
- Who sponsors each mentor is read from the **Sponsors** screen, from *Approved* sponsors that are not test sponsors. A change there shows at once.

#### Corrections during an institutions sync

A school's details corrected on the site go to Airtable, then to the site's copy. If an institutions sync is reading then, the correction asks for one more run five minutes later. Only one waits at a time, and it keeps to **Automatic sync**. A correction the site could not copy at once is in Airtable already and arrives with the next run.

#### The Slack message for a new institution

After an institutions sync, the site posts on Slack for each institution newly at *Confirmed*. It uses the **Slack webhook** on the **Mentor Status Checker** screen, listed beneath **Tools** in the `WPCredits Program` menu. With none saved, nothing is sent or recorded.

- It gives the name, the city and country, the Administrator where one is assigned, and the new total of confirmed institutions.
- Up to three get a message each; more get one message naming them all.
- Each is announced once. The first run with a webhook records those already *Confirmed* and says nothing.
- A **Confirmed on** date over 30 days ago is recorded silently. An empty date is announced.
- Test institutions are never announced or counted. A refused message is sent again next run.

#### Commands for the command line

- `wp wpcredits sync_mentors` runs the mentors sync. Add `--dry-run` to change nothing, `--accept` to pass the count check once, `--restart` to start again during a run.
- `wp wpcredits mentor_airtable_row <reference>` prints the Mentors row a test application would write, and writes nothing.
- `wp wpcredits mentor_airtable_row --check --email=<address>` writes a test row to the Mentors table, reads it back and deletes it. Run it before switching on **Make mentors from applications**. After an error, delete any row left in Airtable.
- `wp wpcredits setting get <key>` prints a setting, a secret masked. `wp wpcredits setting set <key> <value>` changes it by the screen's rules; add `--dry-run` to only show the change. For a **Who is mailed** list, `everyone` means every Administrator.
- Change a live setting one way at a time: an open settings tab saves its own copy.
- `wp wpcredits start_pages` makes the Start here page (`/start-here/`) and the Team Matcher page (`/find-your-team/`), keeping pages that exist. The Team Matcher page needs the Find Your Team plugin active.

### Invitations

An invitation is an email with a link to set a password. Each new link cancels the one before, so only the newest email works.

- In bulk: switch **Invitation emails** on before a sync (`WPCredits Program > Settings`, tab **Students and mentors**). It is off by default, since a first sync creates around ninety accounts.
- By hand: **Send invite** or **Resend invite** on a row of the **Accounts** tab of the Students, Mentors, Institutions and Sponsors screens. Several ticked rows go under **Bulk actions**.
- A bulk send is queued and goes out ten at a time. A card counts what is sent and left.

#### When an invitation is not sent

- A second invitation to the same person within 15 minutes is not sent. The screen says so, and a bulk send leaves them out.
- An invitation the mail host refuses does not count as sent, and the 15 minutes do not start. It has still canceled the earlier link, so send another once the email works.
- A finished bulk send counts how many were not sent. They were refused, already being sent, invited in the last 15 minutes, gone, or held for an Administrator.
- The **Log** of `WPCredits Program > Tools > Emails` shows each refusal. It keeps 30 days.

#### The mentor's invitation

**Invitation: mentor** also carries the welcome. It names the two Start here posts, the five student courses, the mentors' private Slack channel and the mentor resource folder.

- The courses' invitation links and the folder's link are private. An Administrator types them into the template, over its line in square brackets, before any mentor is sent it.
- Once you save an edit, the site sends your edit instead of the shipped text.
- A reply goes to **Contact address in emails** (`Tools > Emails`, tab **Signature and footer**), while it holds an address.

Activating a mentor application queues this invitation whatever **Invitation emails** says, but never for an Administrator's account. It is held when the account is linked by WordPress.org username alone, or another account also answers for the applicant. Confirm the account, then press **Send invite** on its row in the Mentors screen's **Accounts** tab.

The **Activated** view of the Mentors screen's **Waiting for review** tab says what became of each one.

#### A student's invitation from the Institution Dashboard

A student with no sign-in on record has a line on their card. It says when the last invitation went, with **Resend invitation** (**Send invitation** when none went).

- The school's representatives can press it, and so can an Administrator who opened the dashboard. Tutors cannot. No Administrator is emailed.
- The 15 minutes apply, and each school can send 20 a day. A press that sends nothing does not count.
- A held invitation shows no button: the card says the Administrators send it once they confirm the account.
- The school learns only whether a sign-in is on record, never when.
- Each send is in the **Log**, and in the **History** card under **For Administrators**.

Step by step: [[Invite a student, or several]]; [[A student the sync skipped, and a held invitation]]; [[A held invitation, and an Airtable row that was refused]]; [[Change the words of an email and test it]].

### Who follows an institution up

A school's assigned Administrator is its contact at the program. **Areas of responsibility** decide who it is (`WPCredits Program > Settings`, tab **Institutions**): the country's own Administrator first, then the region's.

Once any area is set, the Countries table in Airtable no longer decides who follows a school up. Only an Administrator can be assigned. Someone who stops being an Administrator stays on the record but is nobody's contact.

| When | Who it gets | Who is emailed |
|---|---|---|
| A school applies on this site | Its area's Administrator | That Administrator alone (**Institution assigned, to the Administrator**) |
| No area covers the applicant | Nobody | **Who is emailed about institutions**, or every Administrator when it is empty |
| The application is approved | The application's Administrator, or else today's area Administrator | Nobody |
| The sync reads a school added in Airtable | Its area's Administrator | That Administrator |
| You change it on the details card | Whoever you choose, or **Nobody** | The new Administrator, unless they made the change |

- An application held for review or filed as spam emails no Administrator. When the assigned Administrator's email fails, nobody else is emailed, and the application is marked.
- Approval and the sync never replace an Administrator the school has, or **Nobody**. The sync assigns only schools it had not read before, and none on its first read or after the stored list of schools was rebuilt.
- The sync sends at most five emails a run, and none for a test institution. With more than ten new schools in one read, it assigns none.
- A school no area covers stays unassigned until you assign it. Each change is in the school's history.

**Who is emailed about institutions** still decides who hears about the rest: agreements to review, their reminders, template problems and an institution's last member leaving.

The Institutions screen's queues and the Administrator Dashboard's application card say who an application is assigned to. The school's Institution Dashboard shows its Administrator under **Your contact at the program**, with their booking link.

#### Seeding the areas and assignments

`wp wpcredits institutions_seed`, run on the server, fills the areas, booking links, assignments and tutor lists from Airtable.

- `--dry-run` writes nothing and names each school it cannot assign, and why. `--yes` skips the question "Write this to the site?".
- It changes nothing in Airtable, and sends no email.
- It gives each country the Administrator whose address matches its Countries contact. It leaves regions empty, and writes no area while the site holds any.
- It never replaces what is already there, so it can run again.

#### Tutors

An invitation to a school has a **Level**: a **Representative** works the page, and a **Tutor** only reads the students, their Student Report Cards and the semester reports. Until the agreement is accepted, a tutor sees no more than a visitor. Each level has its own invitation email, and **Resend** keeps the level.

The school's tutor list is the names a student can pick in the **Tutor** box, kept in the **Tutors** section of the **Representatives and tutors** card. **Add a tutor** sends no email and makes no account, and **Remove** keeps any account. A tutor who accepts a **Tutor** invitation is added to it.

Step by step: [[Give a country or a region to an Administrator]]; [[Close a "no area" gap]]; [[Assign a school to another Administrator, or to nobody]]; [[Choose who is emailed about institutions, mentors and students]]; [[Run the seed command]]; [[Invite a representative or a tutor for a school, and keep its tutor list]].

### Pages the plugin owns

Activating the plugin creates these pages when they are missing. A page already at the address is used, and its text is not changed.

| Page | Address | Who can read it |
|---|---|---|
| Student Report Card | `/student-dashboard/` | Students |
| Mentor Report Card | `/mentor-dashboard/` | Mentors |
| Institution Dashboard | `/institution-dashboard/` | Institution accounts |
| Sponsor Dashboard | `/sponsor-dashboard/` | Sponsor accounts |
| Administrator Dashboard | `/administrator-dashboard/` | Administrators |
| Institution application | `/institution-application/` | Anyone |
| Mentor application | `/become-a-mentor/` | Anyone |
| Sponsor application | `/sponsor-application/` | Anyone |
| Student registration | `/student-registration/` | Anyone, never cached |
| Partners | `/partners/` | Anyone, while **Sponsor tiers** is on |

In Pages, the three application pages are titled "Bring WordPress Credits to your institution", "Become a mentor" and "Sponsor the WordPress Credits Program".

- Administrators can read every page. `/apply/` takes a visitor to the institution application.
- If a page goes missing, re-activate the plugin. The screen that uses it, and any Settings row that switches it on, say it is missing.
- The registration form's line for a school not on the list leads to the Student Interest Form while the **Student interest form** switch is on (tab **Students and mentors**) and its page is published. Else it leads to the institution application while that page is published. With neither, it asks the student to have a faculty member or the administration office get in touch.

The plugin makes no page for the Student Interest Form: add the **Student Interest Form** block to a page. Each interest is a private record on the site, never in Airtable. A spam one is deleted 30 days after it was marked as spam, a closed one 365 days after it was closed.

The Administrator guide is at `/administrator-guide/`, and `/program-manager-guide/` leads there.

The Institution Dashboard's guide button follows a published page at `institution-guide` on this site when there is one, and the handbook's institutions page until that page exists. The Sponsor Dashboard's guide button follows a published page at `sponsor-guide` on this site when there is one, and the handbook's sponsor guide until that page exists. Each release republishes both pages from the built Institution guide and Sponsor guide, so a change made to either page by hand lasts only until the next release.

The Mentor Report Card's button does the same with `mentor-guide` and the handbook's Mentor guide. The Student Report Card's does so with `student-guide` and the Student guide.

Step by step: [[Open student registrations]]; [[Put the Student Interest Form on a page]]; [[Open the institution application form]]; [[Open applications to mentor]].

### Uninstall

Uninstall is deleting the plugin, not only deactivating it. What it deletes cannot be brought back.

Kept:

- Every account. One that held only a program role becomes a Subscriber.
- Every page the plugin made, the site's posts, and Media Library files, except sponsor logos nobody approved.
- The signed agreement files of schools and sponsors, with their key. A list of them is emailed to the site's **Administration Email Address** (`Settings > General`).

Deleted:

- The settings, your email edits and the email log.
- The plugin's roles, and every **Program access** level, so gated pages become public.
- The program details on accounts: record IDs, program, mentor links, availability, sign-in times, reminders.
- The records of institutions, students, mentors and sponsors: applications, agreement records, invitations, history, semester reports, registrations, interests, calls, notes, offers, codes, claims, reports and events.
- Track Builder tracks, header notices and the scheduled jobs.

Step by step: [[Before you uninstall the plugin]].

### When something is wrong

| What you see | What it means | What to do |
|---|---|---|
| A student or mentor has no account | The sync makes accounts only for the statuses you chose. | Compare their Airtable status with **Currently mentoring**, **Past students** or **Mentor status to sync** (tab **Students and mentors**). |
| A Student Report Card or Mentor Report Card shows old details | It shows what the last sync stored. | Run the sync by hand. |
| A mentor sees the wrong students | The mentor link in Airtable is wrong. The page follows records, not names. | Fix the link in Airtable, then sync. |
| Nobody can book a call | The mentor set no availability. Their Mentor Report Card says so. | Ask the mentor to set it. |
| Invitations do not arrive | *Handed to the mail server* means the site passed it on. The rest is the mail service's. | Read the **Log**, with **Area** set to **Invitations**. |
| A password link says it is invalid | They opened an older invitation, or the link expired. | Ask them to use the newest email, or "Lost your password?" on the login page. |
| A gated page is read by the wrong people | Its **Program access** level or the reader's role is not as expected. | Check both. Administrators read every level. |

Step by step: [[A student the sync skipped, and a held invitation]]; [[Run a sync by hand and read its report]]; [[Find out what the site sent to someone]]; [[Make a post readable by one group]].

## Semester reports

The site drafts each institution's report for a semester once it has ended.

- A daily job drafts it when every student is finished, or after **Drafting grace** if some are not. That is 7 to 365 days, 45 by default.
- It drafts at most ten a run, only while **Drafting** is on (on by default).
- It drafts only for schools at one of the **Institution pipeline stages** (tab **Advanced**), and never where a report already exists.
- It skips a semester that ended before it first ran. Press **Draft now** on the Institutions screen's **Semester reports** tab instead, at most 20 times a day per account.
- A job's draft is emailed to **Who reviews reports**, or every Administrator when it is empty.
- These settings are in `WPCredits Program > Settings`, tab **Institutions**, section **Semester reports**.

You review the draft on the school's Institution Dashboard, then press **Approve this report**.

- The school sees it from then, with **Download PDF**, and its accounts are emailed. With no account, the screen says so: send the PDF yourself.
- Approval is refused while the students' consent answers cannot be read.
- **Reopen for editing** makes it a draft again, out of the school's view.

Step by step: [[A semester report: draft, review, approve, reopen]].

## The Administrator Dashboard

The Administrator Dashboard is the page at `/administrator-dashboard/`. Only Administrators can open it. Every number on it is read when the page loads.

### The dashboard's views

The page sits in the dashboards' frame: a top bar, a menu down the side and a main area as wide as the window.

- The top bar shows the words Education Directory, a **Homepage** link, and your photo and first name with **Log out** behind them. When the theme draws its own header, the frame shows only a slim bar with the **Menu** button.
- **Collapse menu** folds the menu to its icons, and the browser remembers it. On a tablet the menu shows icons alone; on a phone it opens from **Menu**.

| View | What it holds | Its count in the menu |
|---|---|---|
| **Overview** | The figures, then what waits for a decision | Red: the decisions that wait, as "2 wait" |
| **Watch** | Cards to keep an eye on this week | Amber: how many of **Offers running low**, **Duplicated students**, **Syncs and health** and **Students gone quiet** hold something |
| **Cohort timeline** | The next twelve months of students against the mentor places | None |
| **The program** | Three cards of numbers, then **Tools from our sponsors** with every live offer | None |
| **Program updates** | The newest update for Administrators, while there is one | None |

Under **In wp-admin**, **Mentors** counts the mentor applications waiting. The Administrator guide and each program update open in the same frame and menu.

### The figures at the top

The Overview opens with how many decisions wait for you. The next line names the queue that holds the oldest: "The oldest has waited 67 days, in Semester reports." **Review the oldest** opens that card, or `Mentors > Waiting for review` for a mentor application. With nothing waiting, it reads "Nothing waits for you today".

- The total adds each queue's number. **Semester reports** adds its reports to review, or its semesters due for drafting when none waits for review. So the total can be lower than the rows added up.
- Test institutions' items are left out of the total. They are also left out of the oldest, except a test institution's application, which can still be the oldest.

Under the head come up to four figures. The two shares show only while the homepage shows them, so one can be missing:

- the share of students who graduate rather than drop out;
- the share of graduates who would recommend the program, from the final survey;
- the active mentors, Administrators left out, with how many were seen (a sign-in or a visit) in the last 30 days;
- the programs running: tracks with a student in progress, and at how many institutions.

A line under them says when the two shares were read, and that the mentors and programs are as of today. With no share shown, it says only the second.

### The dashboard's cards

**Overview**, **Watch** and **The program** are lists of cards. Each card is one row, folded until you press it.

- The number beside the title is how many things the card holds. The words after it say where it stands, such as "2 waiting".
- On the Overview, a row also shows its oldest item's wait in red, as "67 days waiting". **Mentor applications** shows none.
- A link in an email, a decision's way back and **Review the oldest** each open the card they land on.
- Each card that waits for a decision has a **How to do this** link to its job in this guide. So do **Upcoming cohorts**, **Duplicated students**, **Syncs and health** and **Students gone quiet**.

A card with a home in wp-admin ends its body with a link there. On **The program**, a folded card also shows the link under its box:

| Card | Its link opens |
|---|---|
| **Institution applications**, **Collaboration Agreements**, **Requests from institutions** | **Open on the Institutions screen**, tab **Waiting for review** |
| **Semester reports** | **Open on the Institutions screen**, tab **Semester reports** |
| The sponsor queues | **Open on the Sponsors screen**, tab **Waiting for review**, at that queue's own card |
| **Offers running low**, **New interests**, **Sponsor reports** | **Open on the Sponsors screen**, tab **Offers and codes**, **Interests** or **Reports** |
| **Sponsor terms ending**, **Sponsors** | **Open on the Sponsors screen**, tab **Sponsors** |
| **Duplicated students** | **Open the Student Duplicate Finder** |
| **Upcoming cohorts** | **Open the cohort timeline** |
| **Mentor engagement** | **Open the Mentors screen's Engagement tab** |

#### Limits and what is left out

- **Institution applications**, **Sponsor applications**, **Sponsor posts to review**, **Sponsor offers to review** and **Sponsor Collaboration Agreements** list the 50 oldest. A line under the list says how many more wait, "listed here as these are decided". It links nowhere.
- **Collaboration Agreements** and **Requests from institutions** list up to 200, with no such line.
- **Offers running low**, **New interests**, and the folds of returned, revoked or out-of-force agreements stop at 50 without a word.
- **Sponsor posts to review**, **Sponsor offers to review** and **Sponsor Collaboration Agreements** count up to 200, then read *200+*, as the Sponsors screen does. **Sponsor applications** counts every one.
- A test institution, one whose name starts with TEST, has its items listed with the **Test** mark and left out of every count.
- Test sponsors are not left out: their posts, offers, agreements and applications are counted and listed like any other.
- **Requests from institutions** shows the 20 latest closed requests under **Recently closed**.

#### The cards to watch

| Card | What it holds |
|---|---|
| **Upcoming cohorts** | Students starting in the next six months; shown once the students sync has read a roster |
| **Offers running low** | Live code offers with fewer codes than their warning, fewest first |
| **Duplicated students** | What the Student Duplicate Finder's last scan found, as of that day |
| **New interests** | What sponsors wrote on their dashboards in the last 30 days |
| **Sponsor terms ending** | Approved sponsors whose term ends in the next 60 days, soonest first |
| **Syncs and health** | The syncs, each with an **Open** link to its screen; **Locked accounts**; private storage; the last emails |
| **Students gone quiet** | See below; shown only while **Daily list** is not *Off* |

**Syncs and health** counts what needs a look:

- While **Automatic sync** is on: a students, mentors or institutions sync whose last run left an error, or that finished no run in six hours.
- While it is off: only a sync whose last run left an error. The row's words begin "Automatic sync is off".
- Either way: the outcome figures' weekly read, when its last read was held or failed, or its figures are more than 29 days old and the homepage shows none.

### Where each queue is decided

**Waiting for you**, on the Overview, holds the queues, oldest first in each. Only a queue with something in it is drawn, as a row with **Review**. One sentence under the rows names the empty queues, each a link to its screen.

- A queue your last decision emptied is drawn once more, open, with the outcome.
- **Events** is a queue only while **Sponsor tiers** is on.

| Queue | Where you decide it |
|---|---|
| **Institution applications**, **Collaboration Agreements**, **Requests from institutions** and the sponsor queues | On its card here |
| **Mentor applications** | **Review** opens `Mentors > Waiting for review` |
| **Semester reports** | **Review** opens the report, where **Approve this report** is. **Draft now** makes a draft. |
| **Sponsor reports** | **Open** opens a draft to finish. **Draft now** makes one. |

- **Approve** on an institution application creates its Airtable record and its site account. **Approve** on a sponsor application creates the sponsor record and its site account.
- **Reinstate** on an institution's Collaboration Agreement is on this page only. **Revoke this agreement** is on `Institutions > Agreements` only.
- An item past the 50 a card lists is decided on its wp-admin screen.

Step by step: [[A school applied]]; [[A signed Collaboration Agreement arrived]]; [[Take an agreement out of force, or put it back]]; [[A semester report: draft, review, approve, reopen]]; [[A company applied to sponsor]]; [[A sponsor's annual report]]; [[Find an application or agreement past the fifty the dashboard lists]].

#### A school's request to correct its details

**Requests from institutions** holds a school's request to correct its name, website, city, country or contact's email. The school sends it from its details card with **Ask to correct these** and **Send to an Administrator**.

- A school has one such request open at a time.
- **On record now** is read when the page loads, so a change made in Airtable since the school asked shows.
- The school can press **Withdraw it** until someone decides. The people in **Who is emailed about institutions** are then emailed, or every Administrator when it is empty.
- If two Administrators decide at once, only the first press acts. The second is told so.
- The Institutions screen's **Waiting for review** tab lists it as **Details to correct**.
- Under **Recently closed** it reads *Approved*, *Declined* or *Withdrawn*. Other requests read *Handled* or *Declined*.

The card also holds *A student to add*, raised for each student a school's confirmed import creates, and any older *A mentor is wanted*. You close them with **Mark as handled** or **Decline**.

Step by step: [[A school asks to correct its details]]; [[A school's other requests (a student to add, a mentor wanted)]].

### The cohort timeline's figures

The **Cohort timeline** view shows the next twelve months, the current one first, under half-year bands. The **Upcoming cohorts** card on **Watch** shows the next six. Both only read.

- The card's number is the students starting in the next six months, confirmed and in review.
- Its words name the first short month ("2 more mentors needed by March 2027"), else the first tight month, else "The mentors cover the next six months".

| Layer | Who it counts |
|---|---|
| **Already on the program** | A student whose status is in **Currently mentoring**, with a start date today or earlier |
| **Confirmed, starting** | The same, with a start date after today |
| **Registration in review** | A Students row with no status, or a registration from the site whose student the students sync has not read yet |

**Currently mentoring** is the list of statuses in `Settings > Students and mentors`, section **Who is on the program**.

- A student counts in every month their dates overlap.
- A month is short when the confirmed students alone are more than the places, and tight when only the registrations in review take it over.
- **Mentor places** is the active mentors times **Students per mentor** (`Settings > Students and mentors`, section **Cohort planning**: 1 to 20, default 3). It is the same every month.
- **Mentors to recruit** covers the busiest of the next six months, registrations in review included. The card's "needed by" counts confirmed students only.
- With no end date, a student counts for four months from the start. With no start date, a student is not counted.
- A student still in progress after their end date counts in the current month only.
- Interested leads, `SPAM`, `Duplicated`, past statuses and test institutions are left out.

Under each month of the timeline's chart, a plus sign gives the students starting and a minus sign those finishing. A short or tight month carries a flag with how many students are over. In **Month by month**, **Balance** compares the places with every student, in review included.

Step by step: [[Read the cohort timeline: do we need more mentors?]].

### The quiet-students card

**Students gone quiet** lists students on a track with no activity for the **Days of quiet** (`Settings > Students and mentors`, section **Quiet students**: 7 to 60, default 14). It shows only while **Daily list** is *Preview* or *On*.

- Activity is a sign-in, a visit, a call or group session, a report saved on the site or edited in Airtable, or **We've already talked**.
- The days count from the student's last activity, or from the later of the account's creation and the track's start when nothing came after it.
- A student with a call or group session booked ahead is never listed.
- The list is made once a day at 06:00 UTC. The card's words give the count and that day.
- Rows are grouped by the Administrator the student's institution is assigned to: **Your students**, each other Administrator, then **No Administrator assigned**.
- A test institution's student has the **Test** mark and is left out of the count.

| Column | What it shows |
|---|---|
| **Last seen** | The last activity and its date, or **Track started** or **Account made** when nothing came after |
| **Mentor** | The mentor, or **No mentor** |
| **Reminder** | **Would remind** in *Preview*. After a reminder, its date and "no reply". Or why no email goes: **Not mailed: test institution**, **Not mailed: quiet before the switch went On**. |

**We've already talked** counts as activity and takes the student off the list at once. The student, their mentor or any Administrator may press it. On the Report Cards it shows only while **Daily list** is *On*.

Step by step: [[Students who have gone quiet]]; [[Try the quiet-students list in Preview]]; [[Switch the quiet-students reminders on]].

### The program view's cards

**The program** holds three cards, each folded.

#### The Sponsors card

It shows **Approved sponsors**, **With an account**, **Live offers** and **Claims this semester**, then the Approved sponsors by tier. A voided claim is not counted. Test sponsors are counted like any other.

#### The Programs running card

The card reads the students sync's copy of the rosters, so it is as old as the last students sync. The line under the table gives the oldest read.

- Per track: students in progress, and how many signed up this semester, a start date in the current half-year.
- **Finished this semester** counts status `Graduate` with an end date in the semester, across every track. A student who dropped out is not counted.
- **Mentors** counts distinct mentor names, so two mentors with one name count once.
- **Agreement** reads *Not started*, *Template generated*, *Awaiting review*, *Returned*, *Revoked*, *Accepted* or *On file*. **Report** reads *Not drafted*, *Draft* or *Approved*.
- Test institutions are listed and left out of every total.

#### The Mentor engagement card

Active mentors are those Airtable holds as Active. Administrators who also mentor are counted apart, in a line under the figures.

| Figure | What it counts |
|---|---|
| **Seen in the last 30 days** | A sign-in or a visit in 30 days, and in 7 |
| **Took a program action** | A note, a saved schedule, a group session made or a call the mentor canceled; and how many signed in without one |
| **No sign-in on record** | No sign-in or visit since the day the site began recording them |
| **With students now** | Mentors with students, those with weekly hours and those who wrote notes |
| **Calls held** | Every one-to-one call held on the site, whoever the mentor; under it, how many have a note, how many are still to come, and the group sessions held |

Step by step: [[Find mentors who have gone quiet or have no hours]].

## Sponsors

The companies that sponsor the program, on `WPCredits Program > Sponsors`. First a sponsor's record, accounts, application, logo and agreement; then offers, posts, tiers, reports, events and test sponsors.

### The sponsor's record

Every sponsor is a record on this site, one per company. The site reads no sponsor record from Airtable. They are listed on the tab **Sponsors**, each row with **Edit** and **Manage accounts**.

A record is made one of two ways:
- You approve the company's application. The record starts as *Approved*.
- You fill in **Add a sponsor**, under the table, and press **Add the sponsor**. It starts as *In review* unless you choose another **Status**, and nothing else happens until it is *Approved* and has an account.

The record's form:
- **Name**, **Website**, **Contact person**, **Contact email**. Accounts are made at the contact email.
- **Status**: **Approved**, **In review**, **Paused**, **Not Moving Forward**, **Rejected**. Only an *Approved* sponsor can be given an account.
- **Assigned Administrator**: **Nobody yet**, or an Administrator. The sponsor's mail for the program goes to this person. Their **Booking link**, on their own WordPress profile, lets the sponsor book a call.
- **Interests**: read only, what the sponsor sent.
- **Sponsored mentors**: the mentors the sponsor funds.
- **Notes**: private, shown on this screen only.
- **Test sponsor: not listed publicly**, **Tier** and **Term ends** are explained in the parts on test sponsors and tiers.

Rules:
- An assigned Administrator who lost the role stays on the record, marked *(not an Administrator now)*. They get no mail, and the Sponsor Dashboard does not name them.
- Renaming a sponsor renames its posts' category.
- A save is refused if someone saved the record after you opened it. Your changes stay in the form.
- **Its accounts** (`WPCredits Program > Settings`, tab **Sponsors**, section **When a sponsor is no longer Approved**): what leaving *Approved* does to the accounts. Choices: **Remove its accounts from the sponsor, so they lose access to its Sponsor Dashboard**, **Leave their access in place**. Default: **Leave their access in place**.
- **Who is mailed** (same tab, section **Interest mail**): mailed when a sponsor has no assigned Administrator, and for every new application. Empty means every Administrator.

While older sponsors are still being moved onto the site, the tab shows **Moving sponsors onto the site**. The move retries by itself; **Run again** runs it now. The box names any sponsor whose Administrator was not matched, so you choose one on its Edit form, and any sponsor still waiting for its first draft offer.

The public feed `/wp-json/wpcpm/v1/sponsors` lists *Approved* sponsors and two years of monthly application counts. It has no contact details or test sponsors, and can be fifteen minutes old.

Step by step: [[Add a company that never applied]]; [[Pause or stop a sponsorship]].

### Sponsor accounts

A record and its accounts are separate. An approved application makes the first account. For any other *Approved* sponsor, use **Create account** on the tab **Accounts**, view **No account**.

- **Create account** uses the **Contact email**, at most 10 sponsors a press. The password-set link goes only to an account never invited before.
- **Manage accounts** opens one sponsor's accounts, with **Remove**, and **Attach account** for an *Approved* sponsor only.
- A removed account is never deleted. It loses the Sponsor Dashboard and the right to write posts and upload files.
- A sponsor's name on the tab **Sponsors**, and **View page** on the tab **Accounts**, open its Sponsor Dashboard as that sponsor.

On its Sponsor Dashboard, view **Your company**, a sponsor saves **Website**, **Contact person**, **Contact email** and **Anything else you would like to share**, and its logo. While **Sponsor tiers** is on, a Supporter or higher also saves **Description for the partner page**. Each save is logged with the fields changed. A new **Contact email** does not change who can sign in.

**Talk to your program contact**, shown only while **Sponsor tiers** is on, mails the assigned Administrator, else **Who is mailed**, else every Administrator. Five a day per account.

Sponsors never see a student's name: their mentors card shows names of mentors and counts of students.

Step by step: [[Give a sponsor an account, and manage its people]]; [[Open a sponsor's Sponsor Dashboard]].

### The sponsor application form

Companies apply at `/sponsor-application/`. The form is off until you tick **Applications from sponsors** (`WPCredits Program > Settings`, tab **Sponsors**, section **Applications**). While it is off, visitors read that applications are closed.

It asks the company's name, website, contact person and email, a logo in color and in white, anything else, and the privacy policy box. **Which level are you considering?** is asked only while **Sponsor tiers** is on. A website with a space, a user name or no dot is refused: *Enter a web address such as example.com.*

Checks:
- One network address can send five an hour. More are refused.
- After 40 in a day for the whole site, the rest are held.
- A filled-in hidden field, or a missing or invalid form token, marks it as spam.
- A form sent too fast, three links or more, words on the site's disallowed list, or a company name the same as the contact's, holds it.

| State | The applicant gets | Administrators get |
|---|---|---|
| *new* | An acknowledgement with a reference, such as SAPP-2026-0007 | A mail to **Who is mailed**, else to all |
| *held by the anti-spam checks* | The acknowledgement | Nothing |
| *marked as spam* | Nothing | Nothing |

The logo files stay private until the application is approved.

#### Deciding an application

Read applications on the tab **Waiting for review**, card **Sponsor applications**. Decide them on the Administrator Dashboard, card **Sponsor applications**, which lists the 50 oldest. Past those, decide on the Sponsors screen.

| Mark | What it means |
|---|---|
| *held* | The checks held it. Open it to read why. |
| *possible duplicate* | Another open application names the same company or address. Nothing is merged. |
| *already on the site* | A sponsor with this name or website exists. Approving makes a second record. |
| *already an account* | The contact address has an account. **Approve** is refused. |
| *approval half done* | **Approve** stopped partway. Press it again to finish. |

- **Send this question** takes 10 to 2,000 characters.
- While an approval is half done, **Reject**, **Reject as spam** and **Put back in the queue** are refused.
- **Recently decided**, under the list, holds applications marked as spam, rejected or approved. Open one to press **Delete for good**, or, for a spam or rejected one, **Put back in the queue**. Deleting cannot be undone.

How long applications are kept (`WPCredits Program > Settings`, tab **Advanced**, section **Applications**, shared by all the application forms):
- **Spam kept for (days)**: default 30.
- **Rejected kept for (days)**: default 365. A held application counts from its last change.
- **Approved kept for (days)**: default 0, which keeps them for ever.

A deleted application takes its logo files with it, unless it was approved.

Step by step: [[A company applied to sponsor]]; [[From application to an active sponsor: the whole path]].

### A sponsor's logo and agreement

#### The logo

A sponsor uploads a **Color logo** and a **White logo, optional** on its Sponsor Dashboard, card **Company profile and logo**.

- PNG, JPEG or WebP, at least 300 pixels wide, at most 4000 on a side. SVG is refused. The site checks the file itself, not its name.
- At most **Largest logo** (tab **Sponsors**, section **Offers**), 1024 KB by default. Five uploads a day per company.
- A logo already stored is never refused for its width.
- **Remove it** takes the logo off the site at once. The files stay in the Media Library.

#### The agreement

The Collaboration Agreement is optional. A sponsor's dashboard, offers and codes work without one.

- A sponsor uploads its signed PDF on its Sponsor Dashboard, view **Agreement**. Limits: **Largest upload (MB)**, 10 by default, and **Uploads per day**, 5 by default (tab **Advanced**, section **Agreements**).
- While it waits for review, the sponsor can press **Withdraw it**. The file is deleted at once.
- Its assigned Administrator is emailed, else **Who is mailed**, else every Administrator. Accepting, returning, taking out of force and reinstating email every account of the sponsor.
- Read it on the tab **Waiting for review**, card **Signed agreements**, with what the scan noticed. The download is saved, never opened in the browser.
- Decide it on the Administrator Dashboard, card **Sponsor Collaboration Agreements**. Take it out of force, or record a copy the program holds, on the tab **Agreements**.
- A returned file is deleted after **Discarded files kept for (days)**, 30 by default. An accepted agreement is never deleted.
- Uninstalling the plugin keeps every signed file and mails the site's admin address a list of them.

Step by step: [[A sponsor uploaded its signed agreement]]; [[A sponsor's agreement: record, take out of force, put back]].

### Offers, codes and claims

An offer is what a sponsor gives the program's students. Sponsors manage offers on their Sponsor Dashboard. You see the oldest 100 offers on `WPCredits Program > Sponsors`, tab **Offers and codes**.

Offers are separate from the sponsor tiers: a sponsor may have any number of live offers, whatever its tier.

#### What an offer is

- **Kind**: **A pool of one-time codes, one per person**, or **One code or link everyone uses**. It is fixed once the offer holds a code, a shared code or a claim.
- **Also open to**: **Mentors**, **The program team**. Current students are always in.
- **Warn me when fewer than this many codes are left**: 1 to 1000. It starts at **Low-stock warning** (`WPCredits Program > Settings`, tab **Sponsors**), 10 by default.
- **Last day (optional)**: after this day nobody sees the offer.

When a sponsor is approved or gets an account, the site makes its first offer: an empty draft with its name, marked **(primary)** on the tab. For a sponsor with an account and no offer, the tab has **Create the first offer for** and its name.

| State | What it means |
|---|---|
| *Not switched on yet* | A draft. Only the sponsor sees it. |
| *Waiting for review* | Sent to you. Only the sponsor sees it. |
| *Live* | Students and mentors it is open to see it. |
| *Paused* | Hidden. Its codes stay. |
| *Dormant* | Held back by the program. It can only be ended. |
| *Ended* | Final. Claimed codes stay with their holders. |

An offer goes live only with something to give: a code left in the pool, or a shared code.

#### Codes in a pool

The sponsor pastes codes or uploads a .txt or .csv file, in the offer's **Add codes** box or with a new offer.

- One code per line, or a CSV with the code in the first column. A code can be a whole checkout link.
- At most 200 characters a line, 5,000 codes an offer, and a file of one megabyte. A file is never stored.
- One bad line refuses the whole list. The message names the first ten bad lines and counts the rest. Repeats, and codes the offer already holds or voided, are refused.
- **Void unclaimed codes** voids every code in the pool nobody claimed. Codes waiting for review are left alone. It cannot be undone.

Codes are stored encrypted, and only the person who claimed one sees it. No email carries a code.

#### Who can claim

A person can claim only when all of these hold:

- The offer is *Live* and not past its last day.
- They are a current student, or a mentor or Administrator the offer is open to. A current student holds a status in **Currently mentoring** and not in **Past students** (`WPCredits Program > Settings`, tab **Students and mentors**). A student with no synced status yet is not current.
- The Tools section is on for them.
- The sponsor is not a test sponsor. Administrators may still claim one open to the program team.
- While **Sponsor tiers** is on, a student on a track with partners sees and claims only those partners' offers.
- They have not claimed it, and a code or shared code is left.

A second press shows the same code. A refusal says why.

#### The Tools section

**Tools from our sponsors** lists the offers open to the person, with **Get my code** or **Show me the code**. **Your codes** keeps every code they hold, from ended offers too.

- On a person's own Student Report Card: **Show the section on the Student Report Card**, on by default.
- On a person's own Mentor Report Card: **Show the section on the Mentor Report Card**, off by default.
- On `Administrator Dashboard > The program`: every live offer, always.
- The section is not drawn when the person has no offer open to them and holds no code.
- Both switches are in `WPCredits Program > Settings`, tab **Sponsors**. On your view of a student's card, the section is one line, such as *2 tools claimed*.
- Students and mentors never see a test sponsor's offers, or an empty pool unless they hold one of its codes.

#### Claims, problems and low stock

Sponsors see numbers only, on the **Usage** card, with **Download as CSV**. On the **Offers and codes** tab, **Claimed by** shows each person's name, address, date and the code's last four characters, with **Void**.

- **Report a problem with this code** sits beside each code under **Your codes**. A person can report three a day.
- **Void** lets that person claim again. The code stays void in the counts.
- Nothing about a claim reaches Airtable.

| Email (`WPCredits Program > Tools > Emails`) | Who gets it | When |
|---|---|---|
| **Problem with a claimed code** | The sponsor's assigned Administrator | A person reports a problem |
| **Codes running low** | The sponsor's accounts and its assigned Administrator | A claim drops a pool below its warning; once, until codes are added |
| **Sponsor offer returned with a note** | The person who sent it for review | You return an offer or a change |

With no assigned Administrator, the first two go to **Who is mailed** (tab **Sponsors**, section **Interest mail**), else to every Administrator. For **Codes running low**, the sponsor's accounts are mailed either way.

Step by step: [[Void a code claim]].

#### The review of an offer

A sponsor's new offer waits for your review before students and mentors see it. So does a change to an offer that is *Live*, *Paused* or *Dormant*. This holds whether **Sponsor tiers** is on or off.

- A change to the title, the texts, the link or the shared code is held. Students see the approved offer meanwhile. The sponsor can press **Discard the changes**.
- On a draft, the same change is saved at once. The draft must then be sent for review.
- Pausing, ending, the last day, the audience, the warning and voiding apply at once.

You decide on the Administrator Dashboard card **Sponsor offers to review**. The Sponsors screen's tab **Waiting for review** lists the same items.

- A return note is required, up to 2,000 characters.
- If the offer changed after you opened the page, nothing is decided. A note you typed is kept.

Step by step: [[A sponsor sent an offer for review]].

#### Codes waiting for review

Codes a sponsor adds to a *Live*, *Paused* or *Dormant* offer also wait, since a code can be a link.

- Nobody can claim them yet.
- The item lists them under **Codes added**: how many, how many are web addresses, then up to 20 as plain text.
- **Approve** makes them available. **Return it with this note** takes them out, so the sponsor can add them again. The sponsor's own **Discard the changes** takes them out too. Ending the offer takes them out too.

Codes added to a draft go in with it. A draft the program made then needs review, as after a change to its words.

When you work on a sponsor's Sponsor Dashboard, nothing you do needs review: you see **Switch on** where the sponsor sees **Send for review**.

Step by step: [[A sponsor sent an offer for review]]; [[Open a sponsor's Sponsor Dashboard]].

### Sponsor posts and email

Sponsor posts are never emailed to the site's email subscribers. Jetpack Subscriptions leaves out the *Sponsors* category and every sponsor's category under it. Each sponsor post also carries Jetpack's do-not-email mark before it goes live.

Step by step: [[A sponsor sent a post for review]].

### How the sponsor tiers work

A sponsor's tier says what its sponsorship includes. The plugin holds no amounts and the site handles no money.

#### The tier ladder

Five tiers, lowest first. Each has everything in the tier below, plus its own lines.

| Tier | Adds | Posts at a time |
|---|---|---|
| **Friend** | Company name on the partner page, a mention in the mentor feature, the annual thank you post | None |
| **Supporter** | Logo and description on the partner page, a badge, the annual program report | None |
| **Backer** | Named for the part it funds, a report on that cost line, one educational post | 1 |
| **Champion** | A second post, an invitation to the annual partners session, a co-hosted event | 2 |
| **Partner** | Named as partner of the program it funds and in launch communications, its own report, co-branding on its track | 2 |

- A sponsor with no tier set is a Friend. So is a sponsor whose term has ended.
- Offers are separate from the tiers: no tier caps live offers.

#### The Tier box

On a sponsor's Edit form, after **Assigned Administrator**: `WPCredits Program > Sponsors`, tab **Sponsors**, **Edit** on its row. **Add a sponsor** has no Tier box, so a new sponsor starts as a Friend.

- **Tier**: the sponsor's tier. Choices: **Friend**, **Supporter**, **Backer**, **Champion**, **Partner**. Default: **Friend**.
- **Term ends**: the term's last day. Empty means no term: the tier stands until you change it. A Friend's day is cleared on save.
- **Extra events this term**: for a saved Champion or Partner. Co-hosted events this term beyond the one the tier includes, 0 to 11. Without a term, nothing is saved.
- **What the sponsorship funds**: one line, at most 200 characters. The sponsor sees it from Backer up, never on the Partners page.
- **Tracks**: read only, for a sponsor that is a Partner today. The live tracks that name it, each linked to the Track Builder.

Rules you rely on:
- The last day of a term is still the tier. From the day after, the sponsor reads as a Friend. To renew, move **Term ends** forward.
- Extra events belong to one term. A renewal that leaves the box alone starts the new term with none. The box then says what the old term had, such as *2 more events were granted for the term to January 31, 2027.*
- For a term that has ended, type the number as you move the day: one save grants it to the new term.
- The tier asked for on the application shows here, such as *Requested on the application: Backer.*

The tab's **Tier** column reads, for example, *Backer until January 31, 2027*, or *Friend (Backer ended January 31, 2027)* for a lapsed term.

Step by step: [[Tiers: launch, grace, extra events]].

#### When sponsors see the tiers

Two settings decide it, at `WPCredits Program > Settings`, tab **Sponsors**, section **Tiers**.

- **Sponsor tiers**: the launch switch, **Launch the sponsor tiers**. While it is off, no sponsor sees a tier or meets a cap, and nothing goes dormant or comes back. Default: off.
- **Tier grace date**: through this day nothing goes dormant, even with the switch on. Default: December 31, 2026.

You can set tiers and terms while the switch is off. Administrators see them either way.

While **Sponsor tiers** is on, the sponsor sees:
- **Your sponsorship** on its Sponsor Dashboard: **Sponsorship**, **Events** from Champion up, **Reports** once a report is final, and **Badge** from Supporter up.
- **Description for the partner page** on **Company profile and logo**, from Supporter up, 300 characters at most.
- **Talk to your program contact** on **Sponsorship**. A press files a line such as *wants to move to Backer*, or *wants to renew as Backer* after a term ended. Five a day per account. A Partner has no button until its term has ended.

These lines are mailed as the term mail below is, and listed on `Administrator Dashboard > Watch`, card **New interests**.

#### Post caps and dormant posts

While **Sponsor tiers** is on, a tier caps the posts in review or published. Drafts are never capped.

- With posting on, a Friend or a Supporter sees a sentence in place of **Write a post**.
- A post sent beyond the cap stays a draft, and the **Posts** card says why.
- Your **Publish** is refused once the published posts fill the tier: "Not published: the sponsor's tier includes no more published posts. The tier is on the Sponsors screen."

A published post the tier no longer covers goes *Dormant* on the daily run, only while the switch is on and after the **Tier grace date**.
- It is unpublished and left out of the review queue. The newest posts go dormant first.
- It comes back by itself, oldest first, once the tier has room, usually after you set a higher tier.
- If the sponsor edits it, it becomes a new submission for review.

Step by step: [[A sponsor sent a post for review]].

#### The daily run

Once a day the site checks every Approved sponsor against its tier that day.

- With the switch on and the grace date past, it makes dormant the posts the tier does not cover.
- With the switch on, it brings back dormant posts the tier covers, and every *Dormant* offer not past its last day.
- Whatever the switch, it sends the term mail. It mails nobody about what it moved.

| Email (`WPCredits Program > Tools > Emails`) | When |
|---|---|
| **Sponsor term ending, to Administrators** | Once a term, 30 days or fewer before its last day |
| **Sponsor term ended, to Administrators** | The term ended before that mail went |

Both go to the assigned Administrator, else to **Who is mailed** (tab **Sponsors**, section **Interest mail**), else to every Administrator. A moved **Term ends** day is a new term, mailed again.

Until the grace date has passed, the **Sponsors** tab says above its table what would go dormant the day after.

#### Where Administrators see the tiers

- `Administrator Dashboard > The program`, card **Sponsors**: the Approved sponsors counted by tier.
- `Administrator Dashboard > Watch`, card **Sponsor terms ending**: terms ending in the next 60 days, soonest first.

Step by step: [[Tiers: launch, grace, extra events]]; [[Where a sponsor stands]].

### The Partners page

The public list of partners, at `/partners/`. The site makes it once, when an Administrator first opens wp-admin, with the heading **Our partners**, one sentence and the **Sponsors** block. After that the page is yours to edit. If a page already exists at `/partners/`, the site uses it and writes nothing into it.

The site's theme prints the rest of the page around this content: an introduction, the levels with each level's amount, the products and the ways to apply. Editing the page does not change those parts.

- Turning **Sponsor tiers** on publishes the page. Turning it off returns it to draft.
- While the switch is off, the page cannot be published, and visitors get a "not found" page. Administrators can still read it.

#### The Sponsors block

Only Administrators can add the **Sponsors** block. Its settings:
- **Shape**: **Full list** for the Partners page, or **Strip of logos** for the landing page.
- **Lowest tier shown**: leaves out the tiers below it. The strip ignores it.

What it shows:
- Approved sponsors, never a test sponsor, in alphabetical order, with no tier named.
- A Friend by name. From Supporter up: logo, name linked to its website, and description.
- The full list ends with **Upcoming events** while the switch is on and an event is coming.
- The strip shows the logos from Supporter up, counts the Friends, and links **All partners** while the Partners page is published.

While the switch is off, an Administrator sees the block under a note that visitors do not see it yet.

#### The badge and the Handbook listing

Both work only while **Sponsor tiers** is on.
- The badge, for each Approved sponsor from Supporter up, at `/sponsor-badge/<slug>` and `/sponsor-badge/<slug>-dark`. It names the program, the tier and the year. After a term ends, the sponsor keeps its badge, naming the year the term ended.
- The **Handbook listing** tab on the Sponsors screen: the same list as plain HTML. Press **Copy** and paste it into the handbook or the WordPress Foundation's page.

#### The level question on the application

While **Sponsor tiers** is on, the application asks, optionally, **Which level are you considering?**: the five tiers or **Not sure yet**. While it is off, the form has no section **How you would like to support the program**.

- The answer shows on the **Waiting for review** tab and the Administrator Dashboard, such as *Considering: Backer* or **Tier: not sure yet**, then in the Tier box. It never sets the tier.
- An older application may hold **How would you like to support WP Credits? (older answer, no longer asked)**. Approving copies it to **Sponsorship option**; otherwise that starts empty.

Step by step: [[A company applied to sponsor]].

### A Partner's tracks

A Partner co-brands the tracks that name it. You name them on the track's page in the Track Builder, after **Key chip color**.

- **Partners**: a box for each Approved sponsor that is a Partner today. One the track names already stays, noted *no longer a Partner* or *unknown sponsor*. With neither, the row reads "No sponsor is at the Partner tier yet." and has no box.
- **Partners' narrative**: one text for all the track's partners, rewritten each term, at most 5,000 characters. Each partner's annual report starts its track block with it, from the track as last published. The report is drafted the day after that partner's term ends, so publish the track after a rewrite.

Rules you rely on:
- **Save the track** keeps every ticked partner, qualified or not, and drops one you untick.
- A track names at most 10 partners. A copy starts with no partners and no narrative.
- **History** names a change as **Partners** or **Partners' narrative**.
- Students see the published track's partners. A partner whose term ends, or that is no longer Approved, stops showing by itself.

While **Sponsor tiers** is on, the track's students see **In partnership with** and each partner's logo and name on their Student Report Card. It sits in **My course**, else **My hours**, else **Partners of your track**. While the track names a partner that still qualifies, their **Tools from our sponsors** section offers only its partners' offers.

Step by step: [[Change a track that is live]]; [[Publish a new track]].

### Sponsor events

A Champion or a Partner plans its co-hosted webinar or event on its Sponsor Dashboard, card **Events**. You review it on the Administrator Dashboard, card **Events**. The program can also plan events of its own there.

Nothing of the events shows, and no press on them works, while **Sponsor tiers** is off (`WPCredits Program > Settings`, tab **Sponsors**, section **Tiers**, box **Launch the sponsor tiers**). Every rule below holds while it is on.

#### Who can plan an event

A sponsor's accounts plan, change and cancel its event. You can do the same for it on its Sponsor Dashboard.

- The sponsor must be *Approved*, and its tier on the day must include the co-hosted event: **Champion** or **Partner**. From the day after **Term ends**, the sponsor reads as a Friend and plans nothing.
- One event at a time: nothing new while one of its events is planned or approved and not over.
- While a term runs, the term holds one event, plus the number in **Extra events this term**. Approved events dated in the twelve months that end on the term's last day count. The start must be on or before that last day.
- A sponsor with no **Term ends** day is held only to one event at a time.
- A canceled event uses up nothing. Nor does a planned event that ended without an approval.
- The start must still be to come.
- On the Sponsor Dashboard, one account can plan, change or cancel 10 times an hour, all together.
- A plan you make for a sponsor still waits for approval, like the sponsor's own. The emails say an Administrator acted on the sponsor's behalf.
- The partners session and the launch communications are the program's to plan with the sponsor.

A sponsor set to *Paused*, or whose tier no longer has events, keeps its event on the card until it ends, with **Cancel the event** alone. An approved one reads **Approved, but no longer offered: your company's sponsorship does not include events at the moment.**

Step by step: [[Tiers: launch, grace, extra events]]; [[Open a sponsor's Sponsor Dashboard]].

#### What a plan holds

The sponsor's planner and the program's own form ask the same things.

- **Date** and **Start time**: on the clock of the person planning, which the form names. Everyone who joins sees it on their own clock.
- **Length in minutes**: 15 to 480. The form starts at 60.
- **Places**: 1 to 500, or blank for no limit. A change cannot set fewer places than the people already on the event.
- **Title**: up to 120 characters.
- **Description**: up to 2,000 characters. It is shown on the Partners page once the event is approved.
- **Call link**: an address that starts with https:// or http://, up to 500 characters. Only the sponsor, the people who join and Administrators see it.

Every box but **Places** is required. A refused plan or change comes back with what was typed.

| State | What it means |
|---|---|
| *Waiting for the program's OK* | Planned. Only the sponsor and Administrators see it. |
| *Approved* | Open to join. A later change keeps it approved. |
| *Canceled* | Returned or canceled. It cannot be undone. |

#### The review on the Administrator Dashboard

The card **Events** is on `Administrator Dashboard > Overview`, under **Waiting for you**. It is drawn while a sponsor's event waits, and once more right after any press on it. At other times it is not drawn.

- With nothing waiting, the name **Events** in the line of empty queues opens the Sponsors screen, tab **Waiting for review**. That tab lists the waiting events but decides nothing.
- `WPCredits Program > Overview`, card **Waiting for a decision**, counts them as **Events waiting**.

| Part | What it holds |
|---|---|
| **Waiting for the program's OK** | Every sponsor's planned event not over yet, the oldest plan first, with **Approve** and **Return with a note** |
| **Coming up** | Every approved event not over yet, the soonest first, the program's own included, with **Who joined**, your own **Join** or **Leave**, and, for a sponsor's event, **Cancel with a note** |
| **Plan an event of the program's** | **Plan an event**: see the next part |

Each event shows when it runs on your clock, its places, its description and its call link. A description longer than 300 characters is folded under **Read the description**.

- **Return it with this note** cancels the event and emails the note to the sponsor's accounts. The sponsor sees the note above its planner's form until it plans again or its term is renewed.
- **Cancel with this note** asks first and cannot be undone. Everyone who joined is told.
- Both notes are required, up to 2,000 characters.
- **No longer offered** marks an event whose sponsor is not *Approved*, or has no tier with events today. Waiting, it has no **Approve**: return it with a note. Approved, it stays under **Coming up**, and nobody new can join it.
- **Who joined** names the people on an event. Sponsors see only how many.
- You join an event as yourself.
- While the card is not drawn, you can still cancel a sponsor's approved event on its Sponsor Dashboard, through **Viewing as sponsor**. There is no note, and everyone who joined is told.

Step by step: [[A sponsor planned an event]].

#### Events of the program's own

An event of the program's own has no sponsor. It is named "the WordPress Credits Program" wherever a sponsor's event names its sponsor.

- **Plan the event** saves it approved at once. Nobody is mailed.
- No tier, term or one-at-a-time rule applies. The program may plan any number, for any day still to come.
- It is open to the same people as a sponsor's event. A sponsor's account may join it when that account is also a student, a mentor, an Administrator or an institution contact.
- Under **Coming up** it has **Change the event**, the same form filled in, and **Cancel the event**. The cancel asks first, needs no note and cannot be undone.
- It can be planned, changed or canceled only while the card **Events** is drawn.
- It never counts toward a sponsor's events. It never shows on a Sponsor Dashboard or in a sponsor's annual report.

Step by step: [[Plan or cancel an event of the program's own]].

#### Where people see and join an event

Once approved, an event is listed under **Events from our sponsors** for the people it is for. The heading is **Events from our sponsors and the program** while one of the program's own is listed.

| Who | Where |
|---|---|
| Current students | Student Report Card, under the group sessions in **My mentor call** |
| Active mentors | Mentor Report Card, under the sessions panel |
| Institution contacts, but not tutors | Institution Dashboard, after its sections |
| Administrators | The card **Events**, part **Coming up**, while that card shows |

A current student holds a status in **Currently mentoring** and not in **Past students** (`WPCredits Program > Settings`, tab **Students and mentors**).

- Each item gives the title, the host, when on the person's clock, the length, the places left (a number, **Full**, or **Open to everyone** with no limit) and the description.
- **Join** shows while a place is left. A person on the event sees **You are on it.** and **Leave**. On the three pages other than the card **Events**, they also see **Join the call**; on the card, the call link is on the event.
- Join and Leave work until the event ends. A Leave frees the place at once.
- A sponsor's own accounts never see their own event in a list. It is on their Sponsor Dashboard.
- A test sponsor's events are listed for Administrators alone.
- The list shows only on a person's own page. Through the switcher you see no list on someone else's page.
- When a sponsor stops offering events, its event leaves everyone's list. The people on it still see it, marked **No longer offered**, with **Leave** alone.

#### Events on the Partners page

The Partners page's full sponsor list ends with **Upcoming events**, for everyone, signed in or not.

- It lists every approved event not over yet, the soonest first. A sponsor's event shows while the sponsor still offers events and is not a test sponsor.
- Each gives the title, the host, the day and time on the site's clock with the zone named, the length and the description.
- Addresses that start with http://, https:// or mailto: are taken out of the description. A bare www. address or email address stays as written.
- The page never shows the call link, the places, or who is on an event.
- A visitor who is not signed in gets **Sign in to join**. A student, mentor, institution contact or Administrator gets one link, such as **Join it on your Student Report Card**. Anyone else gets nothing, and so does a person whose page is not published.
- **Join it on your Administrator Dashboard** finds the event only while the card **Events** is drawn.
- The landing page's strip and the **Handbook listing** tab hold no events.

#### Emails about events

Every email about an event is in `WPCredits Program > Tools > Emails`, area **Sponsor events**. Each is written in its recipient's language, with times on their own clock (the site's clock for a bare address). No email names the people on an event.

"The program contact" is the sponsor's assigned Administrator; with none, **Who is mailed** (tab **Sponsors**, section **Interest mail**); with none set, every Administrator.

| Email | Who gets it | When | Calendar file |
|---|---|---|---|
| **Event joined, to the person who joined**; **Program event joined, to the person who joined** | The person, with the call link | They join | Adds it |
| **Event left, to the person who left**; **Program event left, to the person who left** | The person | They leave | Takes it off |
| **Event changed, to the people on it**; **Program event changed, to the people on it** | Everyone on it | An approved event's time, length, title or call link changes | Replaces it |
| **Event canceled, to the people on it**; **Program event canceled, to the people on it** | Everyone on it | The event is canceled, by anyone | Takes it off |
| **Event reminder, to the people on it**; **Program event reminder, to the people on it** | Everyone on it, with the call link | The day before | None |
| **Event reminder, to the sponsor** | The sponsor's accounts not on it, with how many joined | The day before | None |
| **Event approved, to the sponsor** | The sponsor's accounts | You approve | Adds it |
| **Event returned with a note, to the sponsor**; **Event returned with a note, to a sponsor whose tier no longer includes events** | The sponsor's accounts | You return it; the second when the sponsor no longer offers events | None |
| **Event canceled with a note, to the sponsor** | The sponsor's accounts | You cancel it with a note | Takes it off, if it was approved |
| **Approved event changed, to the sponsor**; **Approved event changed by an Administrator, to the sponsor** | The sponsor's accounts | Its approved event is changed on the Sponsor Dashboard | Replaces it |
| **Approved event canceled, to the sponsor**; **Approved event canceled by an Administrator, to the sponsor** | The sponsor's accounts | Its approved event is canceled on the Sponsor Dashboard | Takes it off |
| **Event planned by a sponsor, to Administrators**; **Event planned for a sponsor by an Administrator, to Administrators** | The program contact | An event is planned on the Sponsor Dashboard | None |
| **Waiting event changed by its sponsor, to Administrators**; **Waiting event changed for a sponsor by an Administrator, to Administrators**; **Approved event changed by its sponsor, to Administrators**; **Approved event changed for a sponsor by an Administrator, to Administrators** | The program contact | An event is changed on the Sponsor Dashboard | None |
| **Event canceled by its sponsor, to Administrators**; **Event canceled for a sponsor by an Administrator, to Administrators** | The program contact | An event is canceled on the Sponsor Dashboard | None |

- A reply to a return or to a cancel with a note goes to the Administrator who wrote it.
- The program contact's email gives the call link, the description and how many joined.
- A change of only the description or the places tells nobody on the event.
- A save that changes nothing mails nobody and says **Nothing changed.**
- Of a change or a cancel, the first 50 people on the event are told at once, the rest 50 a minute. Meanwhile the Sponsor Dashboard's message says **Everyone who joined is being told.**

While **Sponsor tiers** is on, the daily run picks the approved events that start 12 to 36 hours later. Their reminders go out shortly after, once per event. An event whose sponsor no longer offers events gets none. An event approved less than about a day and a half before it starts may get none either.

#### What the site keeps of an event

Each plan, approval, return, change and cancel writes a record on the site's audit trail. A join or a leave writes none. The record holds the day, the start, the length, the places and how many were on the event. It never holds the title, the call link, the description, the note or a name. No screen lists these records.

A sponsor's annual report has the table **Events held in this term**, under **Your sponsorship**: **Date**, **Event** and **Joined**. It lists the approved events that ended in the report's period, whatever tier the sponsor held on its last day. With none held, there is no table. It holds no name, no link and no description.

Removing the plugin deletes every event, with the people on it and its call link. The accounts stay.

Step by step: [[A sponsor's annual report]].

### Sponsor reports

The annual sponsor report is the program's account of itself and of one sponsorship, for the twelve months ending on a term's last day. Every report is on `WPCredits Program > Sponsors`, tab **Reports**.

- Supporter and higher tiers have a report. A Friend has none.
- The daily run drafts it once, the day after the term ends, for an Approved sponsor only. It does so whether **Sponsor tiers** is on or off.
- You can draft one for any last day on which the sponsor's tier carried a report.
- No email is sent when a report is drafted or marked final.

#### The Reports tab

The tab lists each Approved sponsor with its reports, each with its state and **Open**. Under them, **Last day of the term** and **Draft a report** draft a report. Where the tier carries no report, the sponsor reads "No report for this tier." instead.

A draft is refused when a report for that day exists, or the tier on that day carries none. While another draft for the sponsor is being made, the screen says "A report for this sponsor is being drafted right now. Try again in a minute."

#### A renewed term still owes its report

When you change **Term ends** before the report exists, the old term keeps its report, whether the day moved forward or back. The daily run drafts it after the old last day, with that term's tier and funded line.

- The Edit form's Tier box shows **Report due**: "A report for the term to June 30, 2027 is still due."
- **Not a renewal** says no report is owed, for a day you only corrected. It asks first.

#### What a report holds

| Section | Tiers | What it holds |
|---|---|---|
| *The program this year* | Supporter and up | The program's figures |
| *Your sponsorship* | Supporter and up | Tier, term, what it funded, mentors, claims, posts, events |
| *The cost line* | Backer and up | Your words |
| *The track* or *The tracks* | Partner | Figures and words for each track it partners |
| *Looking ahead* | All | Your words |

- The figures: students who started, finished and were active, their hours, contributions, teams, institutions and countries. A track has no finished count.
- The hours are the total logged to date by the students active this year, not the hours inside the year.
- A Partner that partners no track has no *The track* section.
- Active means started by the last day and not ended before the first. With no end day, only a status in **Currently mentoring** counts past the period the student started in.
- Mentors are those Active and linked to the sponsor when the report is generated. Claims are counted by calendar month. No student is ever named.
- A track's words start as the track's **Partners' narrative**. After that the report keeps its own.

#### The report's editor

**Open** shows the report in the tab. **Back to the reports** returns to the list.

- The figures are read-only. Each section you write in has a box of up to 5,000 characters.
- **Save the narratives** saves the boxes. Each save that changes a box keeps a saved version.
- **Preview** opens the report as the sponsor prints it.
- **Attach the PDF**, **Replace the PDF** and **Remove the PDF** keep one PDF per report, final or not.
- **Mark final** gives the report to the sponsor. **Reopen** makes it a draft again, hidden from the sponsor.
- **Regenerate**, on a draft only, reads the figures again and keeps the saved narratives.

Save the narratives before **Mark final** or **Regenerate**: words not saved are lost.

#### The report's PDF

- Its size limit is **Largest upload (MB)** (`WPCredits Program > Settings`, tab **Advanced**), 10 by default. A password-protected PDF is refused.
- It downloads as `wordpress-credits-report-<slug>-<last day>.pdf`.
- Administrators can always open the PDF and the print page. The sponsor's members can once the report is final, while **Sponsor tiers** is on.

#### The Sponsor reports card

The Administrator Dashboard's **Sponsor reports** card has three parts.

| Part | What it lists |
|---|---|
| **Drafts to finish** | Every draft, oldest first, with **Open** |
| **Terms ended with no report** | Each term, with **Draft now** |
| **Final this semester** | Reports marked final this semester, with **Open** |

The tile **Sponsor reports to finish** counts the first two parts. With nothing in any part, the card says "Nothing to finish."

Step by step: [[A sponsor's annual report]].

### Test sponsors

A test sponsor is a record for trying the sponsor side of the site. It is never listed publicly.

- **Test sponsor: not listed publicly** is a box on the Add and Edit forms, after **Status**. It is also beside **Approve** on an open application.
- Beside **Approve**, the box is ticked already for a test name: "TEST" in capitals, alone or followed by a space or a sign. "TEST", "TEST: Acme" and "TEST(1)" are test names; "TESTAMENT" and "Test Acme" are not.
- On the **Sponsors** tab its row says "(test sponsor, not listed publicly)".
- A flag you clear stays cleared.

A test sponsor is left out of:

- the Partners page, the logo strip and its count, the handbook listing and the feed at `wp-json/wpcpm/v1/sponsors`;
- everything students, mentors and institution contacts see: its offers, events and posts;
- a track's students, as a Partner. The Track Builder's partner picker still offers it.

Its posts show as not found to everyone but Administrators, its members and the author. The feed's monthly counts also leave out every company with a test name, even with the flag cleared.

Its own accounts see their Sponsor Dashboard. Administrators see it wherever they see a sponsor, and can claim its offers and join its events open to the program team.

To try the sponsor side, approve an application named such as "TEST Acme" with the box ticked, or add the sponsor with the box ticked. Give it an account on the **Accounts** tab before you sign in as it, or open its Sponsor Dashboard.

To see what a student would see, clear the flag for the trial: the sponsor is then public. Tick it again when you are done.

Step by step: [[Add a company that never applied]]; [[Give a sponsor an account, and manage its people]]; [[Open a sponsor's Sponsor Dashboard]].

### The Sponsor Dashboard's cards

You see these cards as the sponsor does when you open its Sponsor Dashboard.

#### The Your sponsorship view

Its cards are drawn only while **Sponsor tiers** is on.

| Card | Drawn |
|---|---|
| **Sponsorship** | Always |
| **Events** | While the sponsor may plan events, or has one still to come |
| **Reports** | Once it has a final report: **Open**, and **PDF** if attached |
| **Badge** | Approved, at Supporter or higher |

- The line under **Your sponsorship** names the cards drawn, such as "Your tier and what it includes."
- **See the program's partners.** links to the Partners page from **Sponsorship** once that page is published. A term that has ended shows no link.
- A tier change shows on **Badge** at once and on the sponsor's own site within an hour. The embed code stays the same.

#### The Posts card

While **Sponsor tiers** is on, at Friend and Supporter, **Posts** is drawn only while the sponsor has a draft, a returned, waiting, held-back or published post. It then says the tier includes no posts. In every other case it is always drawn.

The line under **Offers** ends "and what it wrote for them." only while **Posts** is drawn.

#### Company details and interests

- **Company profile and logo** counts three details: website, contact person and contact email.
- **Type of product** is not on the Sponsor Dashboard. It is on the Edit form and in the **Product** column, and a sponsor's own save never changes it.
- What sponsors would like to support is on the **Interests** tab (the newest 50) and the Administrator Dashboard's **New interests** (the last 30 days). The sponsor's Edit form ticks it under **Would like to support**.
- **Interests** on the Edit form lists, newest first and read-only, all the company told the program from its Sponsor Dashboard. A sponsor that told the program nothing has no such row.

Step by step: [[Open a sponsor's Sponsor Dashboard]].

## Where the plugin keeps its data

This table is for the person who maintains the site's code or database. Nobody needs it to run the program.

It lists many of the names the plugin stores things under, each with the name it has in the code. It is not complete: some stored names have no row here.

| Option | Constant |
| --- | --- |
| `_jetpack_dont_email_post_to_subs` | `WPCPM_Sponsor_Posts::META_NO_SUBSCRIBER_MAIL` (post meta: Jetpack Subscriptions' own mark, `1` on every sponsor post, so it is never emailed to the site's subscribers) |
| `_wpcpm_log_mcp` | `WPCPM_Institution_Audit::META_MCP` (post meta: marks a row of the audit trail as one call to a Claude access ability, answered, refused or failed, so no institution's, sponsor's or program's history lists it; it holds `mcp`, and the call's facts are in the row's data: the ability, the outcome and the reason, the input with every string cleaned, how many rows went out and their record ids (25 at most), the time taken in milliseconds, and the name and id of the application password that signed the call, never the answer and never the password; a refusal by the address's door or its password fence is a row of the same shape with no ability, no input and no rows, and a refusal by the fence also holds the route that was asked for, at most 60 rows an hour for each account (a call refused for being over the hour's, the day's or the lookups' limit counts in the same 60); the newest 100 are listed on Settings > Security under **Latest calls**, and `wp wpcredits mcp_log` lists up to 1,000; they are kept for the days of the **Keep the log for** setting, 90 by default, and the daily sweep, `wpcpm_mcp_log_sweep`, calls `WPCPM_Institution_Audit::delete_mcp_before()`, which deletes the older ones, oldest first, in batches of 500, and no row under another key) |
| `_wpcpm_log_program` | `WPCPM_Institution_Audit::META_PROGRAM` (post meta: marks a row of the audit trail as the program's own, the plan, change or cancel of an event of the program's own, so no institution's or sponsor's history lists it; no screen lists these rows yet, so they are read from the trail itself, the `wpcpm_audit_entry` posts holding this key) |
| `_wpcpm_offer_approved` | `WPCPM_Sponsor_Offers::META_APPROVED` (post meta: `1` when an offer's words have an Administrator's OK, `0` when they wait for it; an offer from before 1.123.18 has none and reads as approved while students can see it) |
| `_wpcpm_offer_codes_held` | `WPCPM_Sponsor_Offers::META_CODES_HELD` (post meta: how many codes a member added to an approved offer wait for review, the mark the review queue finds the offer by; the codes themselves are in the pool's option, `wpcpm_codes_<offer>`, under `held`) |
| `_wpcpm_offer_codes_returned` | `WPCPM_Sponsor_Offers::META_CODES_RETURNED` (post meta: how many held codes the last return took out of the offer, said on the Offers card beside the note) |
| `_wpcpm_offer_proposed` | `WPCPM_Sponsor_Offers::META_PROPOSED` (post meta: a member's change to what students see of an approved offer, held for review, its shared code sealed) |
| `_wpcpm_offer_return_note` | `WPCPM_Sponsor_Offers::META_RETURN_NOTE` (post meta: the note an Administrator returned an offer or a change with) |
| `_wpcpm_offer_returned` | `WPCPM_Sponsor_Offers::META_RETURNED` (post meta: when an Administrator returned it) |
| `_wpcpm_offer_submitted` | `WPCPM_Sponsor_Offers::META_SUBMITTED` (post meta: who sent an offer or a change for review, and when) |
| `_wpcpm_spost_dormant` | `WPCPM_Sponsor_Posts::META_DORMANT` (post meta: the day the daily run put a published sponsor post back to pending) |
| `mcp_call` | `WPCPM_Institution_Audit::KIND_MCP_CALL` (audit kind: one call to a Claude access ability, answered, refused or failed, or one refusal by the MCP address's door or password fence; every row under `_wpcpm_log_mcp` has this kind) |
| `offer_approved` | `WPCPM_Sponsor_Offer_Review::LOG_APPROVED` (audit kind: an Administrator approved a sponsor's offer or a change to it) |
| `offer_returned` | `WPCPM_Sponsor_Offer_Review::LOG_RETURNED` (audit kind: an Administrator returned a sponsor's offer or a change to it with a note) |
| `sponsor_event_approved` | `WPCPM_Sponsor_Event::LOG_APPROVED` (audit kind: an Administrator approved a sponsor's event) |
| `sponsor_event_cancelled` | `WPCPM_Sponsor_Event::LOG_CANCELLED` (audit kind: a sponsor's event was canceled, by one of its members or an Administrator, or an event of the program's own by an Administrator) |
| `sponsor_event_changed` | `WPCPM_Sponsor_Event::LOG_CHANGED` (audit kind: a sponsor's event was changed, by one of its members or an Administrator, or an event of the program's own by an Administrator) |
| `sponsor_event_planned` | `WPCPM_Sponsor_Event::LOG_PLANNED` (audit kind: a sponsor's event was planned, by one of its members or an Administrator, or an event of the program's own by an Administrator) |
| `sponsor_event_returned` | `WPCPM_Sponsor_Event::LOG_RETURNED` (audit kind: an Administrator returned a sponsor's event with a note, which canceled it) |
| `sponsor_report_drafted` | `WPCPM_Sponsor_Report::LOG_DRAFTED` (audit kind: a sponsor report was drafted, by the daily run or an Administrator) |
| `sponsor_report_final` | `WPCPM_Sponsor_Report::LOG_FINAL` (audit kind: a sponsor report was marked final) |
| `sponsor_report_owed_cleared` | `WPCPM_Sponsor_Report::LOG_OWED_CLEARED` (audit kind: Not a renewal cleared a term's owed report) |
| `sponsor_report_pdf` | `WPCPM_Sponsor_Report::LOG_PDF` (audit kind: a sponsor report's PDF was attached, replaced or removed) |
| `sponsor_report_reopened` | `WPCPM_Sponsor_Report::LOG_REOPENED` (audit kind: a final sponsor report was reopened as a draft) |
| `sponsor_tier_run` | `WPCPM_Sponsor_Tiers_Run::LOG_KIND` (audit kind: the daily run's row for a sponsor whose posts it put to dormant, or whose offers or posts it woke; no offer has gone dormant since 1.123.18) |
| `wpcpm_administrator_page_id` | `WPCPM_Administrators_Dashboard::OPT_PAGE` |
| `wpcpm_administrator_page_title_fixed` | `WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_agreement_` | `WPCPM_Institution_Agreement::OPT_PREFIX` |
| `wpcpm_agreement_reminded` | `WPCPM_Institution_Agreement::OPT_REMINDED` |
| `wpcpm_application_log` | `WPCPM_Institutions::OPT_APP_LOG` |
| `wpcpm_application_page_id` | `WPCPM_Institution_Application::OPT_PAGE` |
| `wpcpm_application_page_moved` | `WPCPM_Institution_Application::OPT_MOVED` (the one-time move of the application form's page to its new address, 1.123.27) |
| `wpcpm_badge_rewrite` | `WPCPM_Sponsor_Badge::OPT_REWRITE` (the badge route's rewrite rule, flushed once per site) |
| `wpcpm_booking_url` | `WPCPM_Manager_Booking::META` (user meta: an Administrator's own booking link) |
| `wpcpm_checklist_ticks` | `WPCPM_Student_Checklist::META_TICKS` (user meta: the items of the first-week checklist a student ticked themself, a list of keys among `slack`, `channel` and `learn`) |
| `wpcpm_claim_` | `WPCPM_Sponsor_Codes::LOCK_PREFIX` |
| `wpcpm_claims` | `WPCPM_Sponsor_Claims::META_CLAIMS` |
| `wpcpm_codes_` | `WPCPM_Sponsor_Codes::OPT_PREFIX` |
| `wpcpm_countries` | `WPCPM_Countries::OPT_NAME` |
| `wpcpm_guide_page_moved` | `WPCPM_Settings_Screen::OPT_GUIDE_MOVED` (the one-time move of the Administrator guide's page to its new address, 1.123.41) |
| `wpcpm_handbook_model_fixed` | `WPCPM_Handbook::OPT_MODEL_FIXED` |
| `wpcpm_handbook_page_id` | `WPCPM_Handbook_Assistant::OPT_PAGE` |
| `wpcpm_handbook_page_visible` | `WPCPM_Handbook_Assistant::OPT_APPLIED` |
| `wpcpm_institution_announce_lock` | `WPCPM_Institution_Announce::LOCK` (the lock one announcement holds while it reads the record IDs already announced, posts and writes them back, so two syncs finishing together cannot announce the same institution twice; it holds for two minutes at most and a run that cannot take it announces nothing) |
| `wpcpm_institution_announced` | `WPCPM_Institution_Announce::OPT_ANNOUNCED` (the record IDs of the institutions whose arrival at Confirmed has been announced on Slack, so none is announced a second time) |
| `wpcpm_institution_modules_` | `WPCPM_Institutions_Dashboard::OPT_MODULES_PREFIX` (each institution's module order) |
| `wpcpm_institution_page_id` | `WPCPM_Institutions_Dashboard::OPT_PAGE` |
| `wpcpm_institution_page_title_fixed` | `WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_institutions_index` | `WPCPM_Institutions_Index::OPT_NAME` |
| `wpcpm_institutions_index_lock` | `WPCPM_Institutions_Index::OPT_LOCK` (the lock a correction of one row holds while it rewrites the index) |
| `wpcpm_institutions_last_error` | `WPCPM_Institutions_Sync::OPT_ERROR` |
| `wpcpm_institutions_last_sync` | `WPCPM_Institutions_Sync::OPT_LAST` |
| `wpcpm_institutions_lock` | `WPCPM_Institutions_Sync::OPT_LOCK` |
| `wpcpm_institutions_report` | `WPCPM_Institutions_Sync::OPT_REPORT` |
| `wpcpm_institutions_state` | `WPCPM_Institutions_Sync::OPT_STATE` |
| `wpcpm_invite_queue_lock` | `WPCPM_Mail::QUEUE_LOCK` (a lock row while the invitation queue is written, 30 seconds at most) |
| `wpcpm_mapp_lock_` | `WPCPM_Mentor_Application::LOCK_PREFIX` (the per-application activation locks) |
| `wpcpm_mcp_log_sweep` | `WPCPM_MCP::CRON_SWEEP` (cron hook: the daily sweep of the Claude access log, which deletes the calls older than the **Keep the log for** setting, never fewer than 7 days, in batches of 500, oldest first, up to 20 batches (10,000 rows) a run and the rest the next day, whether the switch is on or off; scheduled on `init` when it is not on the clock, taken off it on deactivation and again on uninstall) |
| `wpcpm_mentor_application_counts` | `WPCPM_Mentor_Application::OPT_KEPT` |
| `wpcpm_mentor_application_log` | `WPCPM_Mentor_Application::OPT_LOG` |
| `wpcpm_mentor_application_page_id` | `WPCPM_Mentor_Application_Form::OPT_PAGE` |
| `wpcpm_mentor_page_id` | `WPCPM_Mentors_Dashboard::OPT_PAGE` |
| `wpcpm_mentor_page_title_fixed` | `WPCPM_Mentors_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_mentors_directory` | `WPCPM_Mentor_Directory::OPTION` (the public list of active mentors: name, WordPress.org profile, country, languages, sponsored or not; written by the mentors sync, read through the filter `wpcpm_public_mentors`) |
| `wpcpm_mentors_field_meta` | `WPCPM_Mentors_Sync::OPT_FIELDS` |
| `wpcpm_mentors_last_error` | `WPCPM_Mentors_Sync::OPT_ERROR` |
| `wpcpm_mentors_last_sync` | `WPCPM_Mentors_Sync::OPT_LAST` |
| `wpcpm_mentors_lock` | `WPCPM_Mentors_Sync::OPT_LOCK` |
| `wpcpm_mentors_lookups` | `WPCPM_Mentors_Sync::OPT_LOOKUPS` |
| `wpcpm_mentors_report` | `WPCPM_Mentors_Sync::OPT_REPORT` |
| `wpcpm_mentors_sponsorship` | `WPCPM_Mentors_Sync::OPT_SPONSORSHIP` |
| `wpcpm_mentors_state` | `WPCPM_Mentors_Sync::OPT_STATE` |
| `wpcpm_mentors_unconfirmed` | `WPCPM_Mentor_Provision::OPT_UNCONFIRMED` (the mentors the sync found by an account's login alone, waiting under Needs confirming; uninstall deletes it) |
| `wpcpm_notices` | `WPCPM_Notices::OPT_NAME` |
| `wpcpm_notices_migrated` | `WPCPM_Notices::OPT_MIGRATED` |
| `wpcpm_notices_plain` | `WPCPM_Notices::OPT_PLAIN` |
| `wpcpm_offer` | `WPCPM_Sponsor_Offers::POST_TYPE` |
| `wpcpm_privacy_version` | `WPCPM_Privacy_Guard::OPT_VERSION` |
| `wpcpm_private_key` | `WPCPM_Secret::OPT_KEY` (aliased by `WPCPM_Private_Files::OPT_KEY`) |
| `wpcpm_private_probe` | `WPCPM_Private_Files::OPT_PROBE` |
| `wpcpm_report_autodraft_since` | `WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE` |
| `wpcpm_report_epoch` | `WPCPM_Semester_Report::OPT_EPOCH` |
| `wpcpm_report_log` | `WPCPM_Semester_Report::OPT_LOG` |
| `wpcpm_report_state_version` | None since 1.123.27: no longer written. It recorded the flip of semester reports marked final to approved in 1.91.0; a site that ran the flip keeps the row until uninstall, which deletes it by name |
| `wpcpm_roles_version` | `WPCPM_Roles::OPT_VERSION` |
| `wpcpm_roster_` | `WPCPM_Roster_Index::OPT_PREFIX` |
| `wpcpm_roster_counts` | `WPCPM_Roster_Index::OPT_COUNTS` |
| `wpcpm_roster_unlinked` | `WPCPM_Roster_Index::OPT_UNLINKED` |
| `wpcpm_sapp_decided_backfilled` | `WPCPM_Sponsor_Application::OPT_BACKFILL` |
| `wpcpm_sapp_lock_` | `WPCPM_Sponsor_Approval::LOCK_PREFIX` (the per-application approval locks) |
| `wpcpm_settings` | `WPCPM_Settings::OPT_NAME` |
| `wpcpm_settings_version` | `WPCPM_Settings::OPT_VERSION` |
| `wpcpm_sevent_drain` | `WPCPM_Sponsor_Event::CRON_DRAIN` (cron hook: tells the people a change or a cancel of a sponsor event left to tell, and the day before's reminders, fifty a run) |
| `wpcpm_sevent_lock_` | `WPCPM_Sponsor_Event::LOCK_PREFIX` (the per-sponsor locks every write to a sponsor's events takes, and `wpcpm_sevent_lock_program`, with `WPCPM_Sponsor_Event::PROGRAM_LOCK`, the one lock the program's own events take) |
| `wpcpm_sevent_remind_retry` | `WPCPM_Sponsor_Event::CRON_RETRY` (cron hook: the one retry of the sponsor events' reminders, five minutes after a run that found a sponsor's lock held) |
| `wpcpm_sevent_reminder_edge` | `WPCPM_Sponsor_Event::OPT_REMINDER_EDGE` (where the last complete window of the sponsor events' reminders stopped, a timestamp: the next run's window starts there when that is sooner than 12 hours on) |
| `wpcpm_sign_ins_since` | `WPCPM_Sign_Ins::OPT_SINCE` (when the sign-ins from before 1.122.26 were read; the Engagement tab's note names it) |
| `wpcpm_sign_ins_version` | `WPCPM_Sign_Ins::OPT_VERSION` |
| `wpcpm_sint_lock_<ID>` | `WPCPM_Student_Interest::LOCK_PREFIX` and the interest's ID (held while one interest's state is changed or it is deleted, 30 seconds at most, and let go after; uninstall removes any left behind) |
| `wpcpm_sponsor_agr_` | `WPCPM_Sponsor_Agreement::OPT_PREFIX` (the per-sponsor transition locks) |
| `wpcpm_sponsor_application_log` | `WPCPM_Sponsor_Application::OPT_LOG` |
| `wpcpm_sponsor_application_page_id` | `WPCPM_Sponsor_Application::OPT_PAGE` |
| `wpcpm_sponsor_co` | `WPCPM_Sponsors_Index::POST_TYPE` (one private post per sponsor; the meta keys are `_wpcpm_sponsor_key` and `_wpcpm_sponsor_row`) |
| `wpcpm_sponsor_event` | `WPCPM_Sponsor_Event::POST_TYPE` (one private post per sponsor event and per event of the program's own, which `_wpcpm_sevent_kind` tells apart, `co_hosted` or `program`, the program's with no `_wpcpm_sevent_sponsor`; its meta keys start `_wpcpm_sevent_`, the call link in `_wpcpm_sevent_link`, one `_wpcpm_sevent_person` row per person on it, one `_wpcpm_sevent_pending` row per person still to be told of a change or a cancel or reminded the day before, and `_wpcpm_sevent_reminded` once they have been written down to be reminded) |
| `wpcpm_sponsor_logo_` | `WPCPM_Sponsors_Index::OPT_LOGO_PREFIX` |
| `wpcpm_sponsor_page_id` | `WPCPM_Sponsors_Dashboard::OPT_PAGE` |
| `wpcpm_sponsor_page_title_fixed` | `WPCPM_Sponsors_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_sponsor_report` | `WPCPM_Sponsor_Report::POST_TYPE` (one private post per sponsor per term; the row keys `report_drafted_for` and `report_owed` live inside `_wpcpm_sponsor_row`) |
| `wpcpm_sponsor_tests_marked` | `WPCPM_Sponsors_Index::OPT_TESTS_MARKED` (set once the upgrade of 1.123.18 has marked the sponsors with a test name as test sponsors) |
| `wpcpm_sponsor_tiers_daily` | `WPCPM_Sponsor_Tiers_Run::CRON_HOOK` (cron hook: the tier ladder's daily run) |
| `wpcpm_sponsors_attention` | `WPCPM_Sponsors::TRANSIENT_ATTENTION` (a transient, a minute long) |
| `wpcpm_sponsors_feed` | `WPCPM_Sponsors_Feed::TRANSIENT` (the public feed's built answer, fifteen minutes) |
| `wpcpm_sponsors_migrate_lock` | `WPCPM_Sponsors_Migration::OPT_LOCK` (held while the migration runs, so two runs never overlap) |
| `wpcpm_sponsors_migrated` | `WPCPM_Sponsors_Migration::OPT_REPORT` |
| `wpcpm_sponsors_migrating` | `WPCPM_Sponsors_Migration::TRANSIENT_RUNNING` (the migration's once-a-minute guard) |
| `wpcpm_sponsors_page_id` | `WPCPM_Sponsors_Page::OPT_PAGE` |
| `wpcpm_sreg_lock_` | `WPCPM_Student_Registration::LOCK_PREFIX` (the per-address locks a registration's write takes, and the hourly job's own lock, `wpcpm_sreg_lock_run`) |
| `wpcpm_sreport_lock_` | `WPCPM_Sponsor_Report::LOCK_PREFIX` (the per-sponsor locks a sponsor report's draft takes, so the daily run and an Administrator's draft cannot store two reports for one term) |
| `wpcpm_start_here_page_id` | `WPCPM_Start_Pages::OPT_START` (the Start here page, at /start-here/; the page stays when the plugin is deleted) |
| `wpcpm_student_checklist_on_since` | `WPCPM_Student_Checklist::OPT_ON_SINCE` (the time the first-week checklist's switch was first saved on; a student is new when the account was made after it or the track started in the 28 days before it) |
| `wpcpm_student_flags` | `WPCPM_Student_Flags::OPT_SNAPSHOT` (the daily list of quiet students the card reads; not autoloaded) |
| `wpcpm_student_flags_daily` | `WPCPM_Student_Flags::CRON_DAILY` (cron hook: the daily run that makes the list and sends the reminders, at 06:00 UTC; scheduled on `init` when it is not on the clock, taken off it on deactivation and again on uninstall) |
| `wpcpm_student_flags_lock` | `WPCPM_Student_Flags::LOCK` (held while the daily run works, ten minutes at most) |
| `wpcpm_student_flags_on_since` | `WPCPM_Student_Flags::OPT_ON_SINCE` (the time of the switch's latest move into On; a quiet stretch already past the days of quiet at that time is never mailed) |
| `wpcpm_student_flags_weekly` | `WPCPM_Student_Flags::OPT_WEEKLY` (the ISO week the Monday email last went out for) |
| `wpcpm_student_int` | `WPCPM_Student_Interest::POST_TYPE` (one private post per interest a student sent through the student interest form; its meta keys start `_wpcpm_sint_`) |
| `wpcpm_student_interest_page_id` | `WPCPM_Student_Interest_Form::OPT_PAGE` (the page an editor placed the student interest form on, kept as pages are saved; the page stays when the plugin is deleted) |
| `wpcpm_student_interests_daily` | `WPCPM_Student_Interest::CRON_DAILY` (cron hook: the daily run that deletes a spam interest after 30 days and a closed one after 365; scheduled on `init` when it is not on the clock, taken off it on deactivation and again on uninstall) |
| `wpcpm_student_languages` | `WPCPM_Student_Airtable_Row::LANGUAGES_OPTION` (the site's copy of the Airtable Languages table, record ID to name, read at most once a day, which the registration form's two language questions and the row written to Airtable use) |
| `wpcpm_student_page_id` | `WPCPM_Students_Dashboard::OPT_PAGE` |
| `wpcpm_student_page_title_fixed` | `WPCPM_Students_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_student_registration_page_id` | `WPCPM_Student_Registration_Form::OPT_PAGE` |
| `wpcpm_students_last_error` | `WPCPM_Students_Sync::OPT_ERROR` |
| `wpcpm_students_last_sync` | `WPCPM_Students_Sync::OPT_LAST` |
| `wpcpm_students_lock` | `WPCPM_Students_Sync::OPT_LOCK` |
| `wpcpm_students_report` | `WPCPM_Students_Sync::OPT_REPORT` |
| `wpcpm_students_state` | `WPCPM_Students_Sync::OPT_STATE` |
| `wpcpm_team_matcher_page_id` | `WPCPM_Start_Pages::OPT_MATCHER` (the Team Matcher page, at /find-your-team/; made only while the Find Your Team plugin's quiz block is available; the page stays when the plugin is deleted) |
| `wpcpm_test_institutions` | `WPCPM_Test_Institutions::OPTION` (the record IDs of the test institutions the institutions sync found, remembered after their rows leave Airtable) |
