## Step by step: students

### From first interest to graduation: the whole path

*When:* you want to know where a student is and who acts next. Part of the path is on the site and part is in Airtable.

1. The student, if their school is not in the program, sends the student interest form: see *A student is interested in a school that is not in the program*.
2. The student fills in the **Student registration** form, or the school enrolls them from its Institution Dashboard: the site writes one row to Airtable's Students table and creates no account. See *A student registered*.
3. You read the registration on `WPCredits Program > Students`, tab **Registrations**: see *A student registered*.
4. You, in Airtable, set the **Status** (the track) and the **Mentor** on the student's row in the Students table; an Airtable automation then creates the student's Students Reports and Feedback rows. A student the school enrolled already has a Status, so set only the Mentor.
5. The site's students sync, every three hours while automatic sync is on or when you press **Sync students now**, creates the account from that Students Reports row: see *A student the sync skipped, and a held invitation*.
6. You send the invitation: see *Invite a student, or several*.
7. The student sets a password and works on their Student Report Card, and you follow them: see *Open a student's Student Report Card* and *Students who have gone quiet*.
8. You, in Airtable, change the **Status** when the student finishes, for example to `Graduate`; a graduate keeps the Student role and the Student Report Card, whatever the setting. The sync does not give the role back to a graduate who had already lost it.

### A student registered

*When:* a student sent the **Student registration** form. You are not emailed; the registration waits on `WPCredits Program > Students`, tab **Registrations**.

1. Open `WPCredits Program > Students` and choose the **Registrations** tab.
2. Read the line under the student's name. **Written to Airtable** means the row is in Airtable's Students table.
3. Open **Answers and consent** to read the answers, among them the track the student asked for, when the form asked for one.
4. In Airtable, on the student's row in the Students table, set **Status** to the track and choose the **Mentor**. To move the student to another mentor later, change the **Mentor** on their Students Reports row: the site reads it from there. Once both mentors' lists show the change, the next sync cancels the student's upcoming one-to-one calls with the former mentor and emails both of them.
5. Wait for the next students sync, or open the **Sync** tab and press **Sync students now**.
6. Open the **Accounts** tab, find the student and press **Send invite** under their name.

**What happens next**

- The student was emailed "Registration received - WordPress Credits Program", which says someone will be in touch by email with the next steps, including their mentor and their course.
- Once Airtable has the student's Students Reports row, the sync creates the account with the Student role and a random password. With **Invitation emails** on, it also queues the invitation; with it off, no email goes.
- The invitation is "Welcome to the WordPress Credits Program", with a link to set a password.

**Good to know**

- **Already has an account** and **Already registered** need nothing from you: the student was emailed a sign-in link or told they are registered already, and no row was written.
- **Could not be written**, and **Waiting to be written** with "Held for an Administrator", have a **Write it now** button that tries again at once. **Spam** has **Put back** and **Delete for good**.
- With **Invitation emails** on, the sync has already queued the invitation, so skip step 6. The site never changes the Students row after it writes it: correct it in Airtable. The form itself must be switched on, and it stays closed while Airtable is not connected, no school is at a ticked stage or the languages are not read yet: see Step by step: running the site.

### A student is interested in a school that is not in the program

*When:* a student sent the student interest form. It waits on `WPCredits Program > Students`, tab **Interests**.

1. Open `WPCredits Program > Students` and choose the **Interests** tab. It opens on the **New** view.
2. Read the interest: **Institution**, **Department**, **Representative**, **Internships or practices** and **Anything else**.
3. Write to the representative yourself, from the address on the row.
4. Press **Mark contacted**.
5. When there is nothing more to do, press **Close**.

**What happens next**

- The student was emailed "We received your interest - WordPress Credits Program". The representative is sent nothing by the site.
- Nothing is written to Airtable, and no account, row or institution is made.
- A closed interest is deleted 365 days after it was closed.

**Good to know**

- **Held** holds an interest one of the form's checks flagged. Read the reason on the row, then press **Mark contacted**, **Close** or **Mark as spam**.
- **Put back to New** works on a held, a spam or a closed interest, and emails nobody.
- A new interest is emailed to the people in **Who hears of a new interest**, or to every Administrator when nobody is set there. Nobody in the program is emailed about one held for review or filed as spam.

### Invite a student, or several

*When:* the sync created accounts that were never invited, or a student lost their invitation. Work on `WPCredits Program > Students`, tab **Accounts**.

1. Open `WPCredits Program > Students`, tab **Accounts**.
2. For one student: find them with **Search students**, then press **Send invite** under their name (**Resend invite** if they were invited before).
3. For several: tick them, choose **Send invite** or **Resend invite** under **Bulk actions**, then press **Apply**.
4. For everyone at one school who was never invited: choose the school in **All institutions**, then press **Filter**.
5. In the **Invitations** card, press the button that counts them, such as *Invite 12 students at Example University who have never been invited*, and confirm.

**What happens next**

- Each student is emailed "Welcome to the WordPress Credits Program". Its password link stops working after a day.
- A bulk send goes out 10 at a time in the background. The **Invitations** card shows the count, and its button *Stop and cancel the remaining 30* stops the rest.

**Good to know**

- Nobody is sent a second invitation within fifteen minutes of the last one. Ask the student to use their newest email.
- Each new invitation replaces the link in the one before it.
- With **Invitation emails** switched on, the sync invites each account as it creates it: the switch is **Invitation emails**, `Settings`, tab **Students and mentors**, section **Accounts**, off by default.

### A student the sync skipped, and a held invitation

*When:* a student is in Airtable but has no account, or the **Invitations** card says some students are left out.

1. Open `WPCredits Program > Students`, tab **Sync**.
2. Read the **Last sync report**, and its **Warnings** under it.
3. For "Airtable has no valid email address", correct the address in Airtable, then press **Sync students now**.
4. For "That is a conflict and not a match", read which account holds the address: a mentor's account, an institution's account, or another role. The site has no control that settles it.
5. For a held invitation, make sure the account belongs to the student, then press **Send invite** on that account's own row.

**What happens next**

- An account in conflict is left as it is, and the same warning comes back at each sync until the conflict is settled.
- **Send invite** on the account's row, on the Students screen or the Mentors screen, sends the invitation at once and lifts the hold.
- The email is the mentor's invitation, "Your mentor account is ready", because the account is also a mentor's.

**Good to know**

- Students have no **Needs confirming** list. The sync finds a student's account by the record it stored, then by email address, never by username.
- A held invitation belongs to a student who also mentors and was made a mentor from an application. A notice may say to send it on the Mentors screen; the same account's row on the Students screen sends it too. See Step by step: mentors, *A held invitation, and an Airtable row that was refused*.
- A sync that stopped with **Run anyway** is covered in Step by step: running the site.

### Find a student

*When:* you need one student's account, or all students at one school or on one track.

1. Open `WPCredits Program > Students`, tab **Accounts**.
2. Type a name, username, email address, institution or record ID, and press **Search students**.
3. Or choose a school in **All institutions** and a status in **All statuses**, then press **Filter**.
4. Or press a chip under **Students by status:** to list the students with that status.
5. Press **Show all students**, shown while a school or status is chosen, to clear the choices.

**What happens next**

- The list shows **Student**, **Username**, **Status**, **Institution** and **Mentor**.
- **Status** is in plain words: a track by its name, *Graduated*, *Not continuing*, *Did not complete*, *Getting started*, any other status as it is, or a hyphen when there is none.

**Good to know**

- A student who is in Airtable but has no account yet is not on this list. See *A student the sync skipped, and a held invitation*.
- On any Student Report Card you can also type a name in **Viewing as student** and press **Show**.

### Open a student's Student Report Card, and what you can change there

*When:* you want to see what a student sees, or act for them.

1. Open `WPCredits Program > Students`, tab **Accounts**, and find the student.
2. Press **View page** under their name.
3. To switch to another student, type a name in **Viewing as student**, pick it, and press **Show**.

You can change:

- The report form, with **Save my report**, and the hours, with **Save hours**. Both are saved to Airtable.
- The feedback forms, with **Save my answers**, except **Permissions**: only the student can answer those.
- Calls: book one in **My mentor call**, which says "Booking on behalf of" the student, or press **Cancel** on a booked one-to-one call.
- **Take them off the session** on a group session they joined. This takes off the student alone; the session goes on.
- **Mark as done** on the last three items of **Your first weeks**, and **We've already talked** on a quiet student's note.
- The order of the modules, with their arrows. The order is kept as the student's own.

**Good to know**

- **My profile** has no editor. Dates, institution, tutor, field of study and status come from Airtable and are corrected there.
- **WordPress.org**, **Slack**, **Contribution teams** and **Personal website** come from the report form. Correct them there.
- The language switcher is never shown to Administrators.

### Students who have gone quiet

*When:* the **Students gone quiet** card on `Administrator Dashboard > Watch` lists students.

1. Open the Administrator Dashboard and choose **Watch** in the side menu.
2. Open the card **Students gone quiet**.
3. Read the group **Your students** first: **Days quiet**, **Last seen**, **Mentor** and **Reminder**.
4. Press a student's name to open their Student Report Card.
5. Get in touch with the student, or with their mentor.
6. Press **We've already talked** on the student's row.

**What happens next**

- The row leaves the card at once: "Noted. NAME is off the list until they go quiet again."
- The press counts as activity, so the count of quiet days starts again.

**Good to know**

- The card shows only while the quiet-students list is in Preview or On: see Step by step: running the site.
- In Preview nobody is emailed, and the **Reminder** column says **Would remind**. In On the student gets "Checking in" emails and you get a Monday list of your quiet students.
- A student with a call or group session booked ahead is never on the card.

### Take a student off a group session

*When:* a student joined a group session they cannot attend, and the session has not started.

1. Open the student's Student Report Card: see *Open a student's Student Report Card*.
2. In **My mentor call**, find the session under **Group sessions with your mentor** or **Group sessions**.
3. Press **Take them off the session**.
4. Confirm "Take them off this session? Their place goes back."

**What happens next**

- The student's place goes back for somebody else. The session goes on for everybody else.
- The student is emailed "You left the group session with" their mentor, with a calendar file that removes it.

**Good to know**

- The button is gone once the session has started.

### A student has duplicate rows

*When:* the **Duplicated students** card on `Administrator Dashboard > Watch` counts students with more than one row in Airtable.

1. Press **Open the Student Duplicate Finder** on the card, or open `WPCredits Program > Tools` and choose **Student Duplicate Finder**.
2. Read the counts: how many are **Ready** and how many **Needs a decision**.
3. Under *Ready: every older row is a clean delete candidate*, tick *Delete the 2 older rows of* a student, or press **Select all ready**.
4. Under *Needs a decision*, read **Why** on each row, then tick under **Delete** only the rows to go.
5. Press **Review selection**.
6. Read the list under **Confirm the delete**, then press the button *Delete 3 rows from Airtable*, which counts the rows.

**What happens next**

- The rows are deleted from Airtable, from whichever of Students, Students Reports and Feedback they were in.
- A sealed copy of each row is kept on the site for 30 days, under **Deleted rows**, with **View copy**.
- A row the site points at is listed but cannot be ticked, and its **Why** says so. One confirmation deletes at most 100 rows.

**Good to know**

- Deleting is switched on in the **Deleting duplicates** setting, in the Settings section above the list on the Student Duplicate Finder. Until then the list reads only.
- There is no automatic restore. Open **View copy** and type back by hand only what is needed.
- A scan runs every three hours. Press **Scan now** for a fresh list.

### Where a student's feedback answers are

*When:* you want to read what a student said about the program.

1. For one student, open their Student Report Card and scroll to **Feedback forms**, under the report form.
2. For every student, open Airtable's Feedback table: one row per student, with columns that start `F1` to `F4`.

**What happens next**

- The forms are **Getting started**, **Half way** and **Finishing up**, or **Leaving the program** alone for a student who left without finishing.
- The next form opens only when every question of the one before has been answered, unless the student has already started it. A follow-up that was never shown, and **Permissions**, do not count.

**Good to know**

- The mentor's page does not show the feedback forms. The school sees only what the student allowed under **Permissions**, in its semester report.
- You can answer for a student, except **Permissions**.

### A student with no institution

*When:* the **Reconciliation** card counts **Students rows with no institution**.

1. Open `WPCredits Program > Institutions` and choose the **Sync and storage** tab.
2. In the **Reconciliation** card, find **Rows with no institution**.
3. In the field beside the student's row, type the school's name and pick it.
4. Press **Link**, and confirm.

**What happens next**

- The site writes the school into the **Educational Institutions** column of the student's Students row in Airtable, and nothing else.
- The student is on that school's roster at once. The row stays on this list until the next sync.

**Good to know**

- A row with a reason beside it has no **Link**, because linking it would make a second Students Reports row. Do what the reason says, in Airtable.
- A row with no usable record ID cannot be linked here. Set **Educational Institutions** on it in Airtable.
