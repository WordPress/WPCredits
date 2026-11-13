## Settings

The program's settings, at `WPCredits Program > Settings`. Seven tabs: **Connection**, **Students and mentors**, **Institutions**, **Sponsors**, **Security**, **Mail** and **Advanced**. The screen opens on **Connection**.

The **Mentor Status Checker**, the **Student Duplicate Finder**, **Need help?** and **Emails** keep their settings on their own screens, under `WPCredits Program > Tools`. Each has a **Settings** section at the top of its screen; for **Emails**, at the top of its **Signature and footer** tab. The Settings screen has no Tools tab.

How the screen is drawn:
- Each section opens with one sentence and a link to its part of this guide, on a site that publishes the guide at /administrator-guide/. A visitor who opens /program-manager-guide/, its address before, is sent to the guide.
- Most settings have one sentence of help. What more there is to know sits in a **Details** fold.
- A value Airtable or the site can list is chosen from the list. When it cannot be read, you type the value.
- A saved value the list lacks is offered, marked *(current)*, so a Save never drops it.

How a Save works:
- Each tab but **Mail** has its own **Save settings**, which saves that tab only.
- A Save writes only what you changed. A setting changed elsewhere after you opened the page keeps that value.
- If you changed it too, the other value is kept and a warning names it. Save again if yours should win.
- A value a setting cannot take, such as a mistyped address, is not saved. The old value stays and the box says why.
- A page left open from before an update saves nothing, and the notice asks you to reload and save again.

Each of the five lists of people to mail has a box above it, **Mail every Administrator account instead of the people below**. Tick it to mail them all: an empty list is refused. On the command line, `wp wpcredits setting set <list> everyone` does the same. Give it another name and it prints the five list names.

Step by step: [[Choose who is emailed about institutions, mentors and students]].

### The Connection tab

How the site reaches Airtable, at `WPCredits Program > Settings`, tab **Connection**. It holds the **Airtable** and **Tables** sections, **Save settings**, and a separate button, **Read the lists from Airtable again**.

Step by step: [[Connect the site to Airtable for the first time]].

#### Airtable

The tokens and the base.

- **Personal Access Token**: the token the site reads and writes Airtable with. It is shown masked. Leave it blank to keep the current token.
- **Schema token**: optional, used by the Track Builder alone to create a track's columns when it is published. It needs the `schema.bases:write` scope and an owner with the base creator role. Blank keeps it; type `remove` to take it away. Without it, publishing lists the columns to create by hand.
- **Token scopes**: the scopes the Personal Access Token needs, in its **Details** fold. You set them on the token in Airtable. The site cannot check them.
- **Base ID**: the program's base, chosen from the bases the token can open. Listing them needs `schema.bases:read`.

| Scope | Needed | What it is for |
| --- | --- | --- |
| `data.records:read` | Required | Reading mentors, students and tutors. |
| `data.records:write` | Required for anything the site writes to Airtable | Student registrations, a mentor's Mentors row, promotions in the Mentor Status Checker and every other change the site makes in Airtable. Without it, promoting fails, but report-only checks still run. |
| `schema.bases:read` | Required for student registrations, optional otherwise | Student registrations are written only with it. It also lists the bases, tables, columns, statuses and stages to choose from: without it, you type them. |

Without `schema.bases:read`, a student registration waits on the site and is not written to Airtable.

A mentor made from an application gets a Mentors row. Without `data.records:write`, the mentor is still made, and Airtable's refusal waits on the application. Without `schema.bases:read`, the row holds only name, profile, email and Status.

Step by step: [[Replace the Airtable token]].

#### Tables

Which table of the base holds what, each chosen with its ID beside its name.

| Setting | What it is for |
| --- | --- |
| **Mentors table** | Mentor records. |
| **Students Reports table** | The internship dates, links and contribution team shown on the mentor page. |
| **Students table** | Read by the syncs. A school's changes to a student are written to it. |
| **Institutions table** and **Institutions name column** | Turns the institution IDs Airtable sends into school names. The institutions sync reads each school's name from the name column, whatever the token's scopes. |
| **Contribution areas table** and **Contribution areas name column** | The same, for the contribution team a record links to. |
| **Tutors table** | Read only by the seed command. Schools keep their tutors on the site. |
| **Feedback table** | The students' survey answers: one row per student, a column per question. |
| **Countries table** and **Countries name column** | Each institution's country name, and each country's contact. |

Rules:
- The mentors sync reads a linked table's primary column when it can read the base's structure, and the name column otherwise.
- When the Institutions or Countries table is chosen from the list, its name column offers **None (the built-in column)**. The site then reads the column called *Name*.
- Once any area of responsibility is set on the **Institutions** tab, it decides who follows a school up. Until then, the Countries table's contact does.
- The bases, statuses and stages are read once a day, the tables and columns every fifteen minutes.
- A failed read is not retried for five minutes, unless you Save this tab or press **Read the lists from Airtable again**.
- **Read the lists from Airtable again** reads them at once, for example after a status is added in Airtable.

Step by step: [[Use a status that was just created in Airtable]]; [[Run the seed command]].

### The Students and mentors tab

Who is on the program and what happens to their accounts, at `WPCredits Program > Settings`, tab **Students and mentors**. Ten sections: **Who is on the program**, **When someone leaves**, **Accounts**, **Landing pages**, **Student registrations**, **Interest from students**, **Quiet students**, **New students**, **Cohort planning** and **Mentor applications**.

#### Who is on the program

The statuses are shared: a current student is anyone a mentor is currently mentoring.

- **Currently mentoring**: students with a ticked status appear under "Currently mentoring" on their mentor's page. The boxes are the Students Reports table's statuses, under **From Airtable**, and each live track's status, under **From the Track Builder**.
- **Past students**: statuses that mean mentoring has finished. These students appear in a separate, collapsed section on the mentor's page. With none ticked, only current students are shown.
- **Mentor status to sync**: only mentors with this Mentors table status get an account. Default: *Active*.

Rules:
- A status in both lists counts as current on the mentor's page, the roster and the syncs. In Tools from our sponsors it does not: a student sees those only when their status is in **Currently mentoring** and not in **Past students**.
- A live track's status cannot leave **Currently mentoring** or join **Past students**. Its box is switched off, with a note.
- A Save that tries it saves everything else, leaves that list as it was, and names the track. Take the track off the live site first.
- A status already in **Past students** can always be taken out.
- The four original tracks always run, so their statuses are never past statuses.
- **Currently mentoring** and **Mentor status to sync** cannot be blank: a sync would read every row of the table. A blank one gets its default back, with a notice.

Step by step: [[Take a track off the live site]].

#### When someone leaves

What the sync does with the account of somebody no longer on the program. The account itself is never deleted.

- **When a mentor is no longer active**: what happens to their account. Choices: **Remove the Mentor role**, **Leave the role in place**. Default: **Remove the Mentor role**. Either way the mentor loses their student list.
- **When a student leaves the program**: their program details are kept either way. Choices: **Remove the Student role, so they lose access to Student-level content**, **Leave the role in place**. Default: **Remove the Student role, so they lose access to Student-level content**.

#### Accounts

Whether new accounts are emailed an invitation, and whether Airtable is read on a schedule.

- **Invitation emails**: the sync emails a password-reset link to each new mentor and student, and to each existing account it makes an active mentor. Default: off. A first sync creates around ninety accounts, so leave it off unless you mean to email them all.
- **Automatic sync**: the students, mentors and institutions syncs run every three hours, the mentors half an hour after the students. A run in progress is left to finish unless it has stalled. A run the count check stopped waits for an Administrator. Default: on.

Rules for invitations:
- They are queued and sent ten at a time.
- An existing account gets one each time it becomes an active mentor. An Administrator never gets one.
- An account matched by username alone is not linked until **Confirm** is pressed under **Needs confirming**, on the Mentors screen's **Sync** tab.
- When one run would make more than five existing accounts active mentors, none is emailed. They wait on the Mentors screen's **Accounts** tab, where **Send invite** sends each.
- A mentor made from an application is invited whatever this says. The exceptions are an Administrator, and an account not yet confirmed as the person's.

Step by step: [[Invite a student, or several]]; [[A held invitation, and an Airtable row that was refused]]; [[A mentor's account waits under Needs confirming]]; [[Run a sync by hand and read its report]].

#### Landing pages

Where mentors and students go when they log in. Under each switch is the page's address. If the page is missing, a sentence says re-activating the plugin recreates it.

- **Mentor landing page**: mentors go to their Mentor Report Card when they log in, and in place of the wp-admin Dashboard. Default: on.
- **Student landing page**: the same for students and their Student Report Card. Default: on.

Rules:
- Only someone still on the program is sent, and only while the page is published.
- A person who followed a link somewhere still lands there, and can still open their own profile screen.
- Anyone who can edit others' posts or manage the program is left alone. A student who can only write posts still goes to the Student Report Card.
- The toolbar link to the page is there whatever the switch says, alone or in the **Dashboards** menu.

#### Student registrations

The form students register through, at `/student-registration/`. Its address is under the switch.

- **Registrations from students**: **Take student registrations through the form on this site**. Default: off.
- **Institutions offered**: the form lists only institutions at a ticked Current Stage. Default: *Confirmed*. When Airtable cannot list the stages, you type one a line.

Rules:
- A registration is written to the Airtable Students table with an empty Status. Nothing is written when the address has an account here or is registered already.
- Every registration is listed at `WPCredits Program > Students`, tab **Registrations**.
- A school with two or more running tracks under **Tracks offered** gets one more question, **Which track would you like to join?**, with **Not sure yet** as a choice. **Tracks offered** is a row on the details card of an institution's page.
- The form is closed while the switch is off, Airtable is not connected, no institution is at a ticked stage, or the languages are not read yet. An Administrator on the page reads which.
- A new partner appears after the next institutions sync, which runs every three hours while **Automatic sync** is on. Test institutions are offered to Administrators only, at any stage.

Step by step: [[Open student registrations]]; [[A student registered]].

#### Interest from students

The form for a student whose institution is not in the program yet. The plugin makes no page for it: add the **Student Interest Form** block, or `[wpcpm_student_interest]`, to a page and publish it.

- **Student interest form**: **Take interest from students through the form on this site**. Default: on. It writes nothing to Airtable and makes no account.
- **Who hears of a new interest**: who gets **New student interest, to Administrators**. It names the institution and its country, and none of the student's answers.

Rules:
- Every interest is kept at `WPCredits Program > Students`, tab **Interests**.
- An interest held for review or filed as spam emails no Administrator. The representative the student names is never emailed.
- While the form is open on a published page, the registration form's line for a missing institution links to it.
- The form is also closed while the countries list is empty. Run the institutions sync on the Institutions screen's **Sync and storage** tab.
- Point **Who hears of a new interest** at yourself before you test the form. With no address chosen, every Administrator is mailed, so a test mails them all.

Step by step: [[Put the Student Interest Form on a page]]; [[A student is interested in a school that is not in the program]].

#### Quiet students

A daily list of students who have gone quiet, and their reminders.

- **Daily list**: **Off** keeps no list, Preview fills the **Students gone quiet** card on `Administrator Dashboard > Watch` without email, and **On** also sends reminders. Choices: **Off**, **Preview: Administrators see the card, nothing is mailed**, **On**. Default: **Off**.
- **Days of quiet**: days without activity that make a student quiet, from 7 to 60. Default: 14.

Who is listed:
- Students on the program with a status on a track, never a paused one.
- Activity is a sign-in, a visit, or a call or group session booked or held. A report saved on the site or edited in Airtable counts, and so does **We've already talked**.
- Days never count from before the account was made or the track started, whichever is later.
- A student with a call or group session booked ahead is not quiet.

Rules:
- The list is made once a day, at 06:00 UTC. Saving **Off** clears it and stops the reminders at once. After Off, Preview or On shows no list until the next 06:00 UTC run.
- A student is reminded once for each stretch of quiet. The stretch ends when they are active after the reminder.
- Only a stretch that reaches the days of quiet after the switch moved to **On** is mailed. With 14 days, a student last active 10 days before is reminded 4 days after.
- A test institution's student is listed with the Test mark, left out of the counts and never mailed.
- The emails are edited at `Tools > Emails`, area **Quiet students**.

| Email | To | When |
| --- | --- | --- |
| **Quiet student reminder, to the student** | The student | Once a stretch. |
| **Quiet students, to the mentor** | Their mentor | After the day's run, listing the students just reminded. |
| **Quiet students this week, to the Administrator** | The Administrator assigned to the institution | First run of each Monday, site time, when they have a quiet student. |

The card's **Reminder** column:

| Mark | Meaning |
| --- | --- |
| *Would remind* | Preview: On would remind this student. |
| *Reminded*, a date, *no reply* | Reminded on that date, and quiet since. |
| *Not mailed: quiet before the switch went On* | Already quiet past the days when On was saved. |
| *Not mailed: test institution* | Never mailed. |

While the switch is **On**:
- The student sees a note in **My mentor call** on the Student Report Card, and the mentor a line on the Mentor Report Card. Both are checked again on each view, but the student's own sign-in and visit do not remove the note.
- **Book a call** is on the student's own note, only when they have a mentor or an assigned Administrator's booking page.
- With neither, the reminder names **Contact address in emails**, when it is set.
- The student, their mentor or an Administrator may press **We've already talked**.

Step by step: [[Try the quiet-students list in Preview]]; [[Switch the quiet-students reminders on]]; [[Stop the quiet-students reminders at once]]; [[Students who have gone quiet]].

#### New students

The first-week checklist: a card, **Your first weeks**, on new students' Student Report Card.

- **First-week checklist**: **Show new students the "Your first weeks" card**. Unticked, nobody sees it. Default: off.

Rules:
- The first Save with the box ticked sets a moment the site keeps. Unticking and ticking again does not move it.
- A student is new when their account was made after that moment, or their track started less than 28 days before it or after it.
- Eight items. The site ticks five as it sees them: the WordPress.org profile on record, a sign-in, the report started, a call booked, a contribution team chosen.
- The student ticks three with **Mark as done**: the Making WordPress Slack, the #wpcredits channel, the Learn course. A tick cannot be undone.
- Once all are done, the card folds to one line. It gates nothing.

Ticking the box makes whichever of the two public pages is missing:
- **Start here**, at `/start-here/`: the first steps.
- **Team Matcher**, at `/find-your-team/`: the Find Your Team plugin's quiz. Made only while that plugin is active.

Rules for the pages:
- `wp wpcredits start_pages` makes a missing page at any time.
- A page at either address is used as it is, and one in the trash is made again. The site never rewrites a page it made.
- Start here is linked from the Student Report Card and the student welcome email (`{start_here_link}`). Team Matcher is linked from the checklist and the registration form's thank-you. Each only while published.
- To use your own words, run the command with the box off. Set both pages to Draft, write, publish, then tick the box.
- Both pages stay when the plugin is deleted.

Step by step: [[Switch on the first-week checklist]].

#### Cohort planning

How many students one mentor takes, for the **Mentor places** on the Administrator Dashboard's **Cohort timeline** view and its **Upcoming cohorts** card on **Watch**.

- **Students per mentor**: from 1 to 20. Default: 3.

The timeline gives each active mentor this many **Mentor places**. A month is *Short* when its confirmed students alone need more places, and *Tight* when the registrations in review would.

Step by step: [[Read the cohort timeline: do we need more mentors?]].

#### Mentor applications

The form people apply to mentor through, at `/become-a-mentor/`. Its address is under the switch.

- **Applications from mentors**: **Take mentor applications through the form on this site**. Default: off.
- **Make mentors from applications**: **Make a mentor of each selected applicant whose course shows, or for whom The course is completed is pressed**. On, the site makes the applicant a mentor, invites them (not an Administrator, nor an account still to be confirmed), and writes their Mentors row in Airtable; off, they wait under **Selected (course pending)**. Default: off.
- **Who is mailed**: who gets **New mentor application, to Administrators**. It names the applicant and their WordPress.org profile, and none of their other answers.
- **Mentors' course invitation link**: the link in the selection email. Only an `https://` link is kept. **Select** is refused while it is empty.
- **Selected applications lapse after (days)**: from 14 to 365. Default: 90. An applicant whose course has not shown by then moves to **Lapsed**, with a dated note, and can be selected again.

Rules:
- Applications wait on the Mentors screen's **Waiting for review** tab. The form writes nothing to Airtable.
- The form is closed while the switch is off, or the countries or contribution teams list is empty. An Administrator who opens the page reads why.
- Point **Who is mailed** at yourself before you test the form. With no address chosen, every Administrator is mailed, so a test mails them all.

On all three forms, the consent box links to the WordPress.org privacy policy, not this site's own.

Step by step: [[Open applications to mentor]]; [[A mentor applied]]; [[The course is completed: make the applicant a mentor]]; [[Bring back a declined, lapsed or spam application]].

### The Institutions tab

How schools join and who follows them up, at `WPCredits Program > Settings`, tab **Institutions**. Six sections: **Applications and enrollment**, **Areas of responsibility**, **Landing page**, **When an institution leaves the pipeline**, **Collaboration Agreements** and **Semester reports**.

#### Institution applications and enrollment

Headed **Applications and enrollment** on the screen.

- **Applications from institutions**: **Take applications through the form on this site** opens the public form at /institution-application/. Default: off.
- **Enrollment lists from institutions**: **Let an institution send a list of students to enroll** adds an **Enroll students** section to the Institution Dashboard. A school chooses the program and the term, then adds one student or sends a CSV. Default: off.
- **Create accounts automatically**: **Let the sync create the first account for a Confirmed institution**, from the Contact Email in Airtable. Default: off.

Rules:
- The old address /apply/ sends visitors to the form. While the box is off, the form says applications are closed.
- Nothing is created until somebody presses **Approve** on the Institutions screen or the Administrator Dashboard. If approval stops halfway, press **Approve** again to finish.
- The consent box links to the WordPress.org privacy policy.
- The contact's role, the students' languages, the signer, their title and the school's WordPress Credits page stay on this site, never in Airtable.
- An enrollment list is shown to the school as understood before anything is created. Each institution gets five checks an hour and 600 students a day. Files are never stored.
- A **Download a sample list** link beside the file box gives a CSV with the headers `name`, `email`, `field_of_study`, `tutor` and one example student, whose row is refused. An optional `profile` column is also read.
- An automatic account is made only for a Confirmed institution with its agreement recorded that never had a member. An address already in use is left alone, and the reason shows on the **No account** view of the Institutions screen's **Accounts** tab. With the box off, accounts are made only when somebody presses **Create account** there.

Step by step: [[Open the institution application form]]; [[A school applied]]; [[Give a school its first account]]; [[Enroll students for a school]].

#### Institution areas of responsibility

Which Administrator a new institution is assigned to: the country's own first, then the region's. Headed **Areas of responsibility** on the screen.

- **Regions**: each of the seven regions gets one Administrator, or **Nobody**.
- **Countries**: one row per country with its own Administrator, who beats the region's. Choose **Nobody** to take the row away.
- **Add a country**: type a country and choose its Administrator.
- **Who has what**: one line per Administrator with their regions and countries.
- **Countries by region**: **Move a country** puts a country in another region. Its fold lists each region's countries, and **No region**. A move is saved with the tab; moving a country back to **The region the plugin ships** takes the move away.

Rules:
- Only an account that can manage the program can be chosen. A country has one Administrator: adding one twice is refused, naming who has it. A refused Save keeps none of the areas.
- A new application is emailed to its assigned Administrator alone. One no area covers goes to **Who is emailed about institutions**.
- A change here reassigns no institution already on the program.
- The Institutions screen's **Pipeline** tab lists the countries no area covers, and marks their institutions *no area*.

Step by step: [[Close a "no area" gap]]; [[Assign a school to another Administrator, or to nobody]].

#### Institution landing page

Headed **Landing page** on the screen.

- **Institution landing page**: **Use the Institution Dashboard page as the institution dashboard** sends institution accounts there when they log in, and in place of the wp-admin Dashboard. Default: on.

A person who logs in from a link that names a page, such as an invitation, goes to that page instead. Mentors, editors and anyone who manages the program are left alone. The toolbar link is there whatever the box says.

#### When an institution leaves the pipeline

What the sync does when an institution's stage is no longer ticked under **Institution pipeline stages** on the **Advanced** tab.

- **Its people**: Choices: **Remove its people, so they lose access to its students**, **Leave their access in place**. Default: **Remove its people, so they lose access to its students**. The accounts are never deleted.

Either way, the agreement stops counting as recorded. People left in place can open the agreement, but nothing else of the institution, until it counts again.

#### Collaboration Agreements

- **Agreement review**: days a signed agreement or an institution's application may wait before the queue marks it overdue and the nightly reminder names the agreement. From 1 to 60. Default: 3. The same number is the working days promised to a school or sponsor that sends a signed agreement.
- **Who is emailed about institutions**: tick Administrators, or type addresses under **Other addresses**. Default: nobody, so every Administrator account is emailed.
- **The agreement wording**: the Google Doc the plugin's copy of the Collaboration Agreement came from, linked from the Agreement template card on the Institutions screen's Agreements tab; Google addresses only. Another link is refused and the old one kept.

**Who is emailed about institutions** gets:
- new applications no area covers, and signed agreements to review;
- the nightly reminder, and the notice that the template cannot be used;
- an institution's last member leaving;
- a school's corrections, and its requests to correct its details.

Nobody named means every Administrator account, technical ones too. To mail them all, tick **Mail every Administrator account instead of the people below**. A list with nobody in it, or an entry that is not an address, is refused and the old list kept. The same holds for **Who reviews reports** and **Who is mailed**.

Step by step: [[Choose who is emailed about institutions, mentors and students]]; [[A signed Collaboration Agreement arrived]]; [[A school asks to correct its details]].

#### Semester report drafting

Headed **Semester reports** on the screen.

- **Drafting**: **Draft each institution's semester report when the semester ends** has a daily job draft them. Off, an Administrator presses **Draft now**. Default: on.
- **Drafting grace**: days after a semester ends the job waits for students still in progress. The draft says how many were. From 7 to 365. Default: 45.
- **Who reviews reports**: who is told of each draft, chosen the same way. Nobody named means every Administrator.

The job drafts only for institutions in the pipeline, at most ten a run.

Step by step: [[A semester report: draft, review, approve, reopen]].

### The Sponsors tab

At `WPCredits Program > Settings`, tab **Sponsors**. Six sections: **Applications**, **Landing page**, **When a sponsor is no longer Approved**, **Interest mail**, **Offers** and **Tiers**.

#### Sponsor applications

Headed **Applications** on the screen.

- **Applications from sponsors**: **Take sponsor applications through the form on this site** opens the public form at /sponsor-application/. Default: off.

Submissions wait on the Sponsors screen's **Waiting for review** tab and the Administrator Dashboard. Nothing becomes a sponsor until approved. While off, the form says applications are closed.

Step by step: [[A company applied to sponsor]].

#### Sponsor landing page

Headed **Landing page** on the screen.

- **Sponsor landing page**: **Send sponsor accounts to the Sponsor Dashboard when they log in**, and in place of the wp-admin Dashboard. Default: on.

The same people are left alone as for institutions.

#### When a sponsor is no longer Approved

What a status change on the Sponsors screen does to a sponsor leaving *Approved*, for example for *Paused*.

- **Its accounts**: Choices: **Remove its accounts from the sponsor, so they lose access to its Sponsor Dashboard**, **Leave their access in place**. Default: **Leave their access in place**. Nothing is ever deleted.

Step by step: [[Pause or stop a sponsorship]].

#### Interest mail

- **Who is mailed**: where a sponsor's mail for an Administrator goes when it has none assigned. Nobody named means every Administrator.

This mail covers a sponsor's interests, a mentor it would like to sponsor, its agreement, code problems, low codes, the thirty-day term notice and its events. New sponsor applications always come here.

Step by step: [[A sponsor would like to sponsor a mentor]].

#### Offers

- **Largest logo**: in KB, for sponsor logos. PNG, JPEG and WebP only. From 100 to 8192. Default: 1024.
- **Tools from our sponsors**: **Show the section on the Student Report Card** (default on) and **Show the section on the Mentor Report Card** (default off). The section lists live offers with their claim buttons. With a box off, nobody there can claim. A mentor sees only the offers whose sponsor opened them to mentors.
- **Low-stock warning**: below this many codes left, the sponsor and its Administrator are mailed once; adding codes arms it again. New offers start with it. From 1 to 1000. Default: 10.

The Administrator Dashboard shows every live offer whatever these say.

Step by step: [[A sponsor sent an offer for review]].

#### Sponsor tiers

Headed **Tiers** on the screen.

- **Sponsor tiers**: ticking **Launch the sponsor tiers** is the launch. It publishes the Partners page; unticking returns it to draft. Its **Details** fold lists the rest it turns on. Default: off.
- **Tier grace date**: through this day, nothing a tier does not cover goes dormant. A value that is not a real date keeps the old one. Default: 31 December 2026.

While it is off:
- Visitors see no Sponsors block. Administrators see it, with a note.
- The sponsor badge, the application's tier question and the **Handbook listing** tab are off.
- Nothing goes dormant or wakes, and no event reminders go out.

Either way, Administrators set tiers and terms on the Sponsors screen, and the thirty-day term mail goes out. Until the grace date, the Sponsors screen shows what would go dormant the day after.

Step by step: [[Tiers: launch, grace, extra events]].

### The Security tab

Who must give a second factor, and who may ask Claude about the program, at `WPCredits Program > Settings`, tab **Security**. Two sections, **Two-factor authentication** and **Claude access (MCP)**. One **Save settings** saves both.

#### Two-factor authentication

Which roles must give a code as well as their password when they sign in. It works only while the Two Factor plugin is active.

- **Roles that must use it**: a box for each role: **Administrators**, **Student**, **Mentor**, **Institution** and **Sponsor**. A person in a ticked role is asked for a code from their next sign-in. Default: **Administrators** and **Institution**.
- **Where it stands**: for each ticked role, how many accounts are covered and how many use an authenticator app. Counted when you open the tab. It appears only while the Two Factor plugin is active and a role is ticked.

Rules:
- Nobody sets anything up first: the code is emailed.
- Each person can set up an authenticator app on their own profile screen. Their sign-in then asks for the app's code first.
- An account that is covered but has no app uses emailed codes.
- A person with several roles is asked when any of them is ticked.
- Untick every box to ask nobody.
- A student can still turn it on for their own account.
- Without the Two Factor plugin, a sentence above the boxes says nobody is asked.

Step by step: [[Require two-factor sign-in for a role]].

#### Claude access (MCP)

Lets the Administrators ticked here ask Claude questions about the program. The site answers from its own records, through the MCP Adapter plugin. Claude only reads: it changes nothing.

- **Turn it on**: the box **Let Claude read the program's figures**. Default: off.
- **Who may connect**: a box for each account that can manage the program, by display name and login. Default: nobody.
- **Limits**: three numbers, each counted per person.
  - **Answers an hour, per person**: 1 to 1,000. Default: 120.
  - **Answers a day, per person**: 1 to 10,000. Default: 600.
  - **Lookups of people an hour, per person**: 1 to 200. Default: 40. It counts only the three questions that look people up.
- **Keep the log for**: a number of days, 7 to 365. Default: 90.

A number outside its range is saved as the nearest end of it.

The sentence under **Turn it on** says where the MCP Adapter plugin stands:

| The adapter | What the sentence says |
| --- | --- |
| Not active | Nothing can connect, whatever the box says. |
| Older than 0.7.1 | Its version, and that nothing can connect. |
| 0.7.1 or later | Its version, and the address Claude connects at. |

The address is the site's `/wp-json/mcp/wpcredits`.

Who gets an answer:
- The person signs in with an application password whose name begins with *Claude*, such as *Claude - laptop*. Each person makes their own on their profile.
- The name may be in any case, but *Claude* must be a whole word: *Claudette* does not count.
- A request with no account is refused and not logged.
- While the switch is off, the address does not exist.
- A browser sign-in, or a password with another name, is refused.
- An account that cannot manage the program, or is not ticked under **Who may connect**, is refused.
- An account that is deleted, or is no longer an Administrator, leaves **Who may connect** at the next Save.

A password named *Claude* opens this address and nothing else. On another REST address it is refused, whether the switch is on or off. Anywhere else, such as wp-admin, it signs nobody in.

Before you deactivate this plugin, delete every application password whose name begins with *Claude*. Without the plugin, such a password opens everything its account can open.

Rules for the limits:
- A lookup of people also counts toward the hour's and the day's answers.
- A question over a limit is refused with a message. It names the limit and the time, in UTC, when the count starts again.
- A refused question counts toward none of the three.

**Latest calls**, under the settings, lists the newest 100 calls in the log. A Save does not change it. With no calls it says *Nothing has been asked yet.*

| Column | What it shows |
| --- | --- |
| **When** | The time, in the site's date and time formats. |
| **Who** | The account's display name, its login if it has none, or *A removed account*. |
| **Password** | The name of the application password, never the password. |
| **Asked** | The question's name. *Refused at the door* for a call refused before it named one. *Refused: another address* for a *Claude* password used elsewhere. |
| **Outcome** | *answered*, *failed*, or *refused* with its reason, such as *refused: hour_limit*. |
| **Rows** | How many of the program's records the answer named. |
| **Time taken** | In milliseconds. |

Rules for the log:
- Every answer, and every refusal of a signed-in account, is logged.
- At most 60 refusals an hour are logged for each account.
- What you typed to find someone is kept in the log, cut to one line of 120 characters.
- A daily job deletes calls older than **Keep the log for**, whether the switch is on or off. It never keeps fewer than 7 days.
- One run deletes at most 10,000 calls, and the next day's run deletes the rest.
- `wp wpcredits mcp_log` lists up to 1,000 calls, newest first: `--limit` (1 to 1,000, default 100) and `--format` (`table`, `json` or `csv`). JSON and CSV also give what was asked and the IDs of the records that went out.

Claude can ask seven questions. The answers come from the site's own records, never from Airtable or WordPress.org.

| Question | What it gives |
| --- | --- |
| **Program counts** | The Administrator Dashboard's figures for the current semester: students per track, mentors, institutions per stage, sponsors per tier, mentor applications and the outcome figures. |
| **What waits for an Administrator** | What the Administrator Dashboard lists as waiting, how many, and where to open it. Also when each sync last ran and runs next. |
| **Mentor engagement** | How many active mentors signed in, were seen, acted or stayed idle, as the **Engagement** tab counts them, as of a day. |
| **One institution** | Up to five matches, by record ID or part of the name: stage, country, city, agreement status and dates, number of members, and students per group. |
| **A student's tracks** | Up to ten people, by email address, WordPress.org username or profile address, Students record ID, or three or more characters of a name. Whether each has an account here, and for each track: its status, institution, cohort, dates, team, hours, reports and mentor's record ID. |
| **A mentor's students** | One mentor, by Mentors record ID, username, email address or WordPress.org profile address. Whether they are active, their last sign-in and visit, their Engagement view, and up to 25 students as the Mentor Report Card lists them. |
| **Mentor applications** | Up to 25 applications in one state, newest first: open, selected or lapsed. For each: the day received, days in the state, country, languages, hours, areas, the teacher answer, whether the address is confirmed, and a *duplicate* and a *held* flag. Also how many are open, selected and lapsed. |

The last three are the lookups of people.

Rules for what an answer holds:
- A person comes back as an ID, never by name. A student is the Students record ID of each track, a mentor their Mentors record ID, an applicant their application's reference.
- No answer holds a person's name, login, email address or WordPress.org username, or an applicant's free-text answer or a note.
- An institution's contact and members are never named.
- Every name and label in an answer is cut to one line of at most 120 characters.
- A Students row marked *Duplicated* or *SPAM* is never a track.
- An application filed as spam, declined or made a mentor is never listed.

To put a name to an ID, search for it on the site:

| Claude gives | Where to search it |
| --- | --- |
| A Students record ID, for a student with an account | **Search students** on the **Student accounts** list of the Students screen. |
| A Students record ID, for any student | **Search** on the roster of the track's institution, on the Institution Dashboard, in the cohort the track started. Type the ID exactly. |
| A Mentors record ID | **Search mentors** on the **Mentor accounts** list, or on the **Engagement** tab of the Mentors screen. |
| An application's reference | **Find by reference** on the **Waiting for review** tab of the Mentors screen. |

- A track with no institution is on no roster. It is listed by name and status, without its ID, under **Rows with no institution** on the Institutions screen's **Sync and storage** tab.
- A mentor made by hand has no Mentors record ID. Find them by username or email address.
- A view of the **Waiting for review** tab draws 50 applications at most. **Find by reference** finds any application whatever its age, except one filed as spam.

Step by step: [[Let an Administrator use Claude with the site]]; [[Take away someone's Claude access]]; [[Find out why a Claude call was refused]].

### The Mail tab

At `WPCredits Program > Settings`, tab **Mail**: two shortcuts to the **Emails** tool, under **Invitations** and **Recent mail**. No settings and no Save.

#### Invitations waiting to be sent

Headed **Invitations** on the screen. When invitations are queued, a line says how many; they go out a few at a time.

**Open the email templates** opens the **Templates** tab of `WPCredits Program > Tools > Emails`. There you change each invitation and **Send me a test** to yourself.

Step by step: [[Change the words of an email and test it]].

#### Recent mail

**Open the email log** opens the **Log** tab of the Emails tool, which keeps every email for 30 days. When any failed in that time, a warning counts them, with **Show the failed emails**.

### The Advanced tab

Eleven rarely changed settings, at `WPCredits Program > Settings`, tab **Advanced**. Four sections: **Pipeline stages**, **Applications**, **Agreements** and **Invitations**. Each number box takes only its range.

Step by step: [[Change how long applications and files are kept]].

#### Pipeline stages

Chosen from the Institutions table's Current Stage options.

- **Institution starting stage**: the stage an approved application's institution is created at in Airtable. Default: *First Contact Made*.
- **Institution pipeline stages**: an institution at a ticked stage is in the pipeline; any other has left it. Default: eight, *First Contact Made* to *Student*.

Neither can be blank: a blank one gets its default back, with a notice. When Airtable cannot list the stages, you type the starting stage, and the pipeline stages one per line.

#### Keeping applications

Headed **Applications** on the screen. For institution, sponsor and mentor applications.

- **Spam kept for (days)**: days after an application is marked spam. From 1 to 365. Default: 30.
- **Rejected kept for (days)**: days after a rejection, decline or lapse; for one the checks held, after its last event. From 30 to 3650. Default: 365.
- **Approved kept for (days)**: days after approval, or after an applicant became a mentor. 0 keeps them forever. From 0 to 3650. Default: 0.
- **Trusted proxy**: the one IP address of the proxy whose forwarded header the forms believe for a sender's address. Leave it empty unless the site is behind a proxy. Default: empty.

Rules:
- Student registrations follow the spam and rejected days too.
- A mentor application whose Airtable row is still to be written is kept, for **Write the Airtable row again** in the **Activated** view of the Mentors screen's **Waiting for review** tab.
- A deleted mentor application still counts in the public feed of application figures.
- **Trusted proxy** refuses a range or anything but one IP address, and keeps the old one.

Step by step: [[Bring back a declined, lapsed or spam application]].

#### Agreement files

Headed **Agreements** on the screen.

- **Largest upload (MB)**: the largest signed agreement an institution or sponsor can upload. It also limits the PDF attached to a sponsor's report. From 1 to 50. Default: 10.
- **Uploads per day**: files one institution or sponsor can upload a day. From 1 to 50. Default: 5.
- **Generations per day**: times one institution can generate its agreement a day. From 1 to 100. Default: 10.
- **Discarded files kept for (days)**: days a withdrawn or returned file is kept. An accepted agreement is never deleted. From 7 to 365. Default: 30.

#### Lapsed invitations

Headed **Invitations** on the screen.

- **Settled invitations kept for (days)**: how long an accepted, canceled or expired invitation to an institution stays listed. From 7 to 365. Default: 30.

Step by step: [[Invite a representative or a tutor for a school, and keep its tutor list]].
