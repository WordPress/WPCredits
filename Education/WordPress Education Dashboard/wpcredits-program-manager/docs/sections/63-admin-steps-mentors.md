## Step by step: mentors

### From application to mentoring: the whole path

*When:* you want to know who does what, and where, from the first application to a mentor with students.

1. The applicant fills in the form on the site's `/become-a-mentor/` page (see *A mentor applied*).
2. The applicant follows the confirmation link in the acknowledgement email (see *The applicant's address is not confirmed*).
3. You read the application on the Mentors screen and press **Select** (see *A mentor applied*).
4. The applicant takes the mentors' course from the link in the selection email, then writes back that it is done.
5. The site, with the Mentor Status Checker, or you, with **The course is completed**, makes the applicant a mentor (see *The course is completed: make the applicant a mentor* and *The Mentor Status Checker: check, then promote*).
6. The site makes or links the account, sends the invitation, and writes the mentor's row in Airtable (see *A held invitation, and an Airtable row that was refused*).
7. In Airtable, the program gives the mentor students; the next mentors sync puts them on the mentor's Mentor Report Card (see *Open a mentor's Mentor Report Card*).
8. The mentor sets their hours for calls; you watch who has not (see *Find mentors who have gone quiet or have no hours*).

**Good to know**

- Moving a student to another mentor is done in Airtable: change the **Mentor** on the student's row in the Students Reports table, and the next sync moves the student.
- Stopping a mentor is done in Airtable: change the mentor's **Status** in the Mentors table to anything but **Active**, and the next mentors sync clears their student list and, by default, removes the Mentor role.
- To see where one mentor or applicant stands, use *Where does it stand?*.

### A mentor applied

*When:* the **Mentors** item in the Administrator Dashboard's side menu shows a count, or `Administrator Dashboard > Overview`, **Waiting for you** shows the **Mentor applications** row.

1. Press **Review** on the **Mentor applications** row, or open `WPCredits Program > Mentors`. The screen opens on **Waiting for review** while an application waits.
2. In the **Waiting** view, read the oldest application: the line under the name, its marks, its answers and its **History**.
3. If it carries **address not confirmed**, stop here and see *The applicant's address is not confirmed*.
4. Type a note in the box above the button you choose, if you want one. A note stays on the site and is never sent. **Mark as spam** has no box.
5. Press one decision:
   - **Select** sends the selection email with the mentors' course link.
   - **Ask for more information** sends a request for more details.
   - **Second opinion** asks a colleague to look. Its note is needed, at least 10 characters.
   - **Decline** sends the decline email.
   - **Mark as spam** sends nothing.
6. For **Select**, **Decline** and **Mark as spam**, accept the question the browser asks.
7. Read the notice at the top: it names the applicant and the reference and says what was sent.

**What happens next**

- **Select**: the applicant gets "WordPress Credits Program - Mentor welcome and mandatory course". The application moves to **Selected (course pending)**.
- **Ask for more information**: the applicant gets "WordPress mentorship application - request for further details". The application stays in **Waiting** and is also listed under **Asked for more information**.
- **Second opinion**: nothing is sent. The application stays in **Waiting** and is also listed under **Second opinion**.
- **Decline**: the applicant gets "Thank you for your interest in the WordPress Credits Program". The application moves to **Recently decided**.
- **Mark as spam**: nothing is sent. The application moves to **Recently decided**.

**Good to know**

- **Select** is refused while **Mentors' course invitation link** is empty. Setting it is in *Step by step: running the site*.
- The Administrators are emailed only about an application that passed every check. One marked **held** waits in the queue with no email to you.
- **Find by reference**, above the views, opens any application by its reference, such as `MAPP-2026-0007`, except one filed as spam.

### The applicant's address is not confirmed

*When:* the application carries **address not confirmed**, or a decision came back with "Nothing was sent. The applicant has not confirmed the address with the link in the acknowledgement...".

1. Open `WPCredits Program > Mentors > Waiting for review` and find the application.
2. Press **Send the confirmation link again**. It is the first button on the application. If the application carries **acknowledgement not sent** instead, press **Send the acknowledgement**.
3. Accept the question the browser asks.
4. Wait for the applicant to follow the link. The mark goes when they do.
5. Decide the application as in *A mentor applied*.

**What happens next**

- The applicant gets the acknowledgement again, "Mentor application received - WordPress Credits Program", with the same link. The link in the first email still works.
- The **History** adds "sent the confirmation link again".
- When the applicant follows the link, the page tells them their address is confirmed, and the mark leaves the application.

**Good to know**

- Until the address is confirmed, **Select**, **Ask for more information** and **Decline** are refused, because each emails the applicant. **Second opinion** and **Mark as spam** still work.
- An application marked **acknowledgement not sent** never had the link. Press **Send the acknowledgement** on it; for one filed as spam, **Put back in the queue** sends it.
- The resend counts against the day's limit on acknowledgements. When it is reached, the notice says "Nothing was sent. The day's limit on acknowledgements is reached..."; try again the next day.

### The course is completed: make the applicant a mentor

*When:* a selected applicant writes that they finished the mentors' course, or their application's course line says "the course shows on the profile".

1. Open `WPCredits Program > Mentors > Waiting for review`, view **Selected (course pending)**.
2. Find the applicant. Read the course line, such as "Checked on ...: the course shows on the profile."
3. Type in the box above **The course is completed** how you know, if you want. It is never sent.
4. Press **The course is completed**.
5. Accept the question "Make ... a mentor now?".
6. Read the notice. It says whether the account was made or linked, whether the invitation went, and whether the Airtable row was written.

**What happens next**

- The site makes the mentor's account, or links the one they have, and gives it the Mentor role. An Administrator's account keeps its own roles.
- The application moves to **Activated**, with "Made a mentor on ...".
- The mentor gets the email "Your mentor account is ready" (the subject starts with the site's name in square brackets), with a link to set a password and the welcome. It is never sent to an Administrator's account, and it is held in two cases: see *A held invitation, and an Airtable row that was refused*.
- The site writes the mentor's row in the Mentors table in Airtable, or sets the row that already holds their address back to active.
- If the applicant is a mentor already, the application is only filed under **Activated** with a note. Nothing is sent or written to Airtable.

**Good to know**

- **The course is completed** shows only while **Make mentors from applications** is on. While it is off, the application says "Making mentors from applications is off until the launch: this applicant waits." Switching it on is for the person who runs the site.
- A selected applicant who drops out: press **Withdraw** with a note of what they wrote. The application moves to **Lapsed** and nothing is sent.
- A selected application whose course has not shown after the days in **Selected applications lapse after (days)**, 90 by default, moves to **Lapsed** by itself.

### The Mentor Status Checker: check, then promote

*When:* you want the site to look for the mentors' course on the WordPress.org profile of every selected applicant at once.

1. Open `WPCredits Program > Tools > Mentor Status Checker`.
2. Press **Run check (report only)**. It reads the profiles. It writes nothing to Airtable and makes nobody a mentor.
3. Wait for "Finished. Checked ... mentors."
4. Read the **Course** column (**Completed**, **Not completed**, **Could not check**) and the **Outcome** column.
5. To promote one person, press **Promote** on their row and accept the question. It checks that person again first. **Promote** is on rows that read **Awaiting promotion** only.
6. To promote everyone marked **Awaiting promotion**, press **Promote all eligible** and accept the question.

**What happens next**

- An applicant selected on the site is made a mentor, as in *The course is completed: make the applicant a mentor*. The row reads **Made a mentor**.
- A mentor read from Airtable has their Airtable status changed to the one chosen under **Promote them to**. The row reads **Promoted**.
- A report-only run writes on each selected application when it was checked and what it found.

**Good to know**

- **Run check and promote to Active** checks and promotes in one go, after one question. Its last word is the status chosen under **Promote them to**, at the top of this screen. Use it only when you do not need to read the report first.
- While **Make mentors from applications** is off, an applicant whose course shows reads **Waiting** and is not promoted.
- The daily check and the Slack message after a promotion are set up by the person who runs the site: see *Step by step: running the site*.

### A held invitation, and an Airtable row that was refused

*When:* an application under **Activated** says "No invitation has gone since this applicant was made a mentor...", or "Airtable refused the row on ...", or the Mentors screen's **Sync** tab says "... made from an application has no Airtable row yet."

To send a held invitation:

1. Open `WPCredits Program > Mentors > Waiting for review`, view **Activated**, and find the mentor.
2. Read the invitation line. It says why it is held: the account was linked by WordPress.org username alone, or another account also answered for the applicant.
3. Open the account from the link on the line that starts "Account:", and check that it is the applicant's.
4. Open the **Accounts** tab, find the mentor with **Search mentors**, and press **Send invite** (or **Resend invite**) under their name.

To write a refused Airtable row:

1. On the same application, read Airtable's words after "Airtable refused the row on ...".
2. Fix what they name. For example, when Airtable holds two Mentors rows with this address, keep one in Airtable.
3. Press **Write the Airtable row again**.

**What happens next**

- **Send invite**: the mentor gets "Your mentor account is ready" now (the subject starts with the site's name in square brackets), and the hold on the account ends.
- **Write the Airtable row again**: the notice says "The Airtable row is written, and the mentor's account holds its record.", or "Airtable refused the row again. The reason is on the application; nothing else changed."

**Good to know**

- The mentor is made either way. A held invitation or a refused row does not undo it.
- A held invitation is sent only from the mentor's own row. **Send invite** on ticked accounts and the **Invitations** card leave it out.
- Pressing **Write the Airtable row again** twice makes no second row.

### A mentor's account waits under Needs confirming

*When:* the mentors sync found an account whose username is a mentor's WordPress.org username, with nothing else to tie them, and the Mentors screen's **Sync** tab shows **Needs confirming**.

1. Open `WPCredits Program > Mentors > Sync`.
2. Under **Needs confirming**, compare the mentor with the account beside them: name, username and address.
3. If the account is the mentor's, press **Confirm**.
4. Accept the question "Make ... a mentor and give the account this mentor's students?".

**What happens next**

- The account is linked to the mentor's record and given the Mentor role. An Administrator's account keeps its roles.
- The next mentors sync gives the account its students.
- While **Invitation emails** is on, the invitation is queued too, unless the account was an active mentor already, is an Administrator's, or has a held invitation.

**Good to know**

- If the account is not the mentor's, press nothing. The sync gave it nothing.

### Bring back a declined, lapsed or spam application

*When:* an applicant appeals a decline, a lapsed applicant comes back, or an application was marked as spam by mistake.

1. Open `WPCredits Program > Mentors > Waiting for review`.
2. Find the application: a declined or spam one under **Recently decided**, below the list; a lapsed one in the **Lapsed** view. **Find by reference** finds a declined or lapsed one too.
3. For a declined or lapsed application, press **Select** and accept the question.
4. For a spam application, press **Put back in the queue**. It asks nothing.

**What happens next**

- **Select** sends the selection email again, with the course link, and the application moves to **Selected (course pending)**.
- **Put back in the queue** moves the application to **Waiting**. If it never had its acknowledgement, the applicant gets it now; otherwise nothing is sent.

**Good to know**

- **Select** here needs a confirmed address, as in the queue.
- **Recently decided** lists only the 50 newest spam and declined applications.
- Spam applications are deleted after the days in **Spam kept for (days)**, 30 by default, and declined and lapsed ones after **Rejected kept for (days)**, 365 by default (`Settings > Advanced`, **Applications**). After that, the applicant applies again.

### Find mentors who have gone quiet or have no hours

*When:* you check which mentors need a word from you. The **Mentor engagement** card on `Administrator Dashboard > The program` gives the figures.

1. Open `WPCredits Program > Mentors > Engagement`, or press **Open the Mentors screen's Engagement tab** on the **Mentor engagement** card.
2. Choose a view above the list:
   - **Not seen in 30 days**: seen before, but no sign-in or visit in the last 30 days.
   - **No sign-in on record**: no sign-in or visit on record.
   - **No program action**: no note, no saved hours, no group session planned, no call canceled.
   - **Students, no weekly hours**: has students now, but no weekly hours for calls.
3. Read the **Last visit**, **Last sign-in** and **Schedule** columns.
4. Write to each mentor yourself.

**What happens next**

- Nothing changes on the site: the tab only reads.
- A mentor with no weekly hours sees "No availability set, so students cannot book a call yet." on their Mentor Report Card. Their students read "Your mentor has not set their availability yet." and cannot book a call.

**Good to know**

- The views count active mentors only. Administrators who mentor are in the **Administrators** view.
- The site sends mentors no reminder about hours or sign-ins.
- **Search mentors** finds one mentor by name.

### Open a mentor's Mentor Report Card

*When:* you want to see what a mentor sees, or help them with notes or hours.

1. Open `WPCredits Program > Mentors > Accounts`.
2. Find the mentor with **Search mentors**.
3. Press **View page** under their name.
4. To change mentor on the page, type a name in **Viewing as mentor** and press **Show**.

**What happens next**

- You see the mentor's students, calls, availability and group sessions.
- You can add a note for any of their students, delete any note, change their availability and cancel their calls.

**Good to know**

- Only Administrators see **Viewing as mentor**.
- Which students a mentor has is set in Airtable, not on this page.
- "No students are assigned to you right now..." means the mentors sync found no students for this mentor in Airtable.

### Read the cohort timeline: do we need more mentors?

*When:* `Administrator Dashboard > Watch`, **Upcoming cohorts**, says "... more mentors needed by ..." or "Tight from ...".

1. Open the Administrator Dashboard and choose **Cohort timeline** in the side menu, or press **Open the cohort timeline** on the **Upcoming cohorts** card.
2. Read the four figures at the top: **Starting in the next 6 months**, **Active mentors**, **Mentor places** and **Mentors to recruit**.
3. Read the sentence under them: it says from which month the mentors are short, or tight, or that they cover the next six months.
4. In **Month by month**, find the first month whose **Outlook** is not **Covered**, and read its **Balance**.
5. If **Mentors to recruit** is above 0, the program needs that many more mentors by the peak. Deciding the waiting mentor applications is one way to add them.

**What happens next**

- Nothing changes: the timeline only reads.
- **Mentor places** is the active mentors times the students each mentor takes.

**Good to know**

- **Short**: the confirmed students alone need more places than the mentors have. **Tight**: they fit, but the registrations in review would not.
- **Without a mentor** counts students with no mentor yet, registrations in review included. Mentors are given in Airtable.
- **Change students per mentor**, under **How these numbers are made**, opens the setting.
