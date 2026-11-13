## Step by step: institutions

### From application to first students: the whole path

*When:* you want to know what comes next for a school, and who does it.

**Approve** is not a spam check. It creates the school's record and its first account, so press it only when the program has decided to take the school.

1. The school sends the application form on the site. Detail: [[A school applied]].
2. The school follows the link in its acknowledgement email to confirm its address. Detail: [[The application's address is not confirmed]].
3. You talk with the school. The acknowledgement email gives them a link to book a call with you, when you have a booking link.
4. You press **Approve**. The site creates the Airtable record and the account, and emails a password link. Detail: [[A school applied]].
5. The school sets its password, signs the Collaboration Agreement and uploads it. Detail: [[A signed Collaboration Agreement arrived]].
6. You press **Accept it**. The site opens the school's account and sets Current Stage to Confirmed in Airtable.
7. The school, or you, enrolls students, if enrolling by list is on. Detail: [[Enroll students for a school]].
8. After the semester the site drafts the semester report, if **Drafting** is on, and you approve it. Detail: [[A semester report: draft, review, approve, reopen]].

**Good to know**

- The booking link in step 3 is the **Booking link** on the assigned Administrator's own WordPress profile. With nobody assigned, it is the country contact's link as it stood when the school applied. With no link, the email leaves the call out.
- A school that came in through the old Airtable form skips steps 1 to 4. Detail: [[A school came in through Airtable, not the site's form]].

### A school applied

*When:* the school is on `Administrator Dashboard > Overview`, card **Institution applications**. You may also get an email "{school} is assigned to you" or "New institution application from {school}".

1. Open `Administrator Dashboard > Overview` and find the school on the **Institution applications** card.
2. Press **Read the application** and read the answers.
3. To ask the applicant something, type it in the box above **Send this question** and press it. The question must be 10 to 2000 characters.
4. Once you have talked with the school and the program wants to take it, press **Approve** and confirm.
5. If the program will not take it, type the reason for the next Administrator, press **Reject** and confirm.
6. If it is not a real application, press **Reject as spam** and confirm.

**What happens next**

- **Approve** creates the school in Airtable, at the stage set in **Institution starting stage** (*First Contact Made* unless changed), with Agreement Status *Not started*. If Airtable already holds the school by name or contact address, that record is used instead. It keeps its own stage and Agreement Status.
- It also makes the school's first account from the contact's address, and queues the email "Your institution account is ready". It tells them to set a password and then do the Collaboration Agreement.
- **Send this question** emails your words as written, with your address to reply to. The state becomes *waiting on the applicant*, and **Put back in the queue** appears on the application: it puts the application back in the queue as a new one.
- **Reject** sends the applicant "About your application to the WordPress Credits Program", with no reason in it. Your reason stays on the site.
- **Reject as spam** sends nothing at all.

**Good to know**

- The site does not check that a call took place. Talking first is the program's rule.
- **Approve** is refused until the applicant has confirmed the address. See [[The application's address is not confirmed]].
- **Approve** is also refused when the applicant's address already has an account on the site: "Nothing was created. An account on this site already holds that address." Look at that account first.
- The Airtable record that **Approve** creates cannot be removed from the site.

### The application's address is not confirmed

*When:* the application shows **Send the confirmation link again**, or **Approve** says "Nothing was created. The address on that application has not been confirmed...".

1. Find the application on the **Institution applications** card.
2. Press **Send the confirmation link again** and confirm.
3. Tell the applicant, by your own email or call, to follow the link in that email.
4. Press the school's name on the card. It opens the application on `WPCredits Program > Institutions`, where you look for "The applicant confirmed their address on {date}." On the card, **Send the confirmation link again** is then gone.
5. Press **Approve**.

**What happens next**

- The applicant gets the acknowledgement again, with the same link. The screen says "The confirmation link was sent again."
- Confirming sends nobody an email. You see it only on the application, where **Send the confirmation link again** is gone.

**Good to know**

- Only the applicant can confirm the address. No Administrator can do it for them.
- The site sends at most 200 acknowledgements a day, and a resend counts. Past that, try again the next day.
- An application marked as spam that was never acknowledged gets the acknowledgement once when you press **Put back in the queue**.

### Finish an approval that stopped halfway

*When:* **Approve** said "Nothing was finished", or the application says "Approving this application stopped partway..." or carries the mark *approval stopped partway*.

1. Find the application on the **Institution applications** card.
2. Press **Approve** again and confirm.
3. Read the message at the top of the page.

**What happens next**

- The steps that already worked are kept. A second press does only the rest.
- On success the screen says "The application is approved..." and the invitation is queued.

**Good to know**

- While the application says the approval stopped partway, **Reject**, **Reject as spam** and **Put back in the queue** are refused until it is finished. If nothing was created before it stopped, the application shows no such line and those buttons still work.
- "The account for that address already acts for a different institution" means pressing again cannot help. Decide who the account belongs to first, and remove it from the other school if this application is the right one.
- "An account on this site already holds that address" means somebody already has an account with it. Find out whose before you try again.

### A school came in through Airtable, not the site's form

*When:* the school applied through the old Airtable form. It is on `WPCredits Program > Institutions`, tab **Pipeline**, and while its Current Stage is empty it is under **Not counted above**, in *No stage*. It is not on **Institution applications**.

1. In Airtable, set the school's Current Stage to a stage listed in **Institution pipeline stages** on `Settings > Advanced`.
2. Open `WPCredits Program > Institutions`, tab **Sync and storage**, and press **Sync institutions now**. The sync also runs by itself every three hours.
3. Open the **Pipeline** tab, find the school under its stage, and press its name. Its Institution Dashboard opens.
4. Read **Assigned Administrator** on its details card. If it says *Nobody yet*, see [[Assign a school to another Administrator, or to nobody]].
5. Give the school its first account. See [[Give a school its first account]].
6. Then the agreement: the school uploads it, or you upload it for them, or you record one on file. See [[A signed Collaboration Agreement arrived]] and [[Record an agreement the program already holds]].

**What happens next**

- Unless **Create accounts automatically** is on and the school is *Confirmed* with its agreement recorded, the site sends the school nothing until you add its account.
- If the record names a country in an Administrator's area, the sync may already have assigned it to them and emailed them.

**Good to know**

- Such a school has no address check and no **Approve** step.
- At a stage outside the active ones, each sync takes away its agreement record. With the default setting it also removes the school's accounts from it. A school with no stage at all is left alone, and so are its people.
- What Airtable's own form sends the school is not part of the site.

### Give a school its first account

*When:* a school has no account on the site. Its **Representatives and tutors** card says "Nobody can act for this institution on this site...". An approved school already has one.

1. Open the school's Institution Dashboard. See [[Open a school's Institution Dashboard]].
2. In **Representatives and tutors**, press **Add or re-add an account on the Institutions screen**.
3. Under **Add an account**, type the person's **Name** and **Email address**.
4. Press **Add account**.

**What happens next**

- The site makes the account and joins it to the school. The screen says "The account was added and an invitation is queued."
- The person gets "Your institution account is ready". Before the agreement is settled it tells them to do the agreement. After, it says the account is open.

**Good to know**

- An address that already has an account is refused, unless it is a former member of this school or a mentor's account.
- For a school at *Confirmed* with its agreement settled, `Institutions > Accounts`, view **No account**, shows **Create account** on its row. It uses Airtable's Contact Email. A row that is not *Ready* says why, for example when the school has had a member before or the address already has an account.
- A school with no stage is left alone, but give it an active stage in Airtable first: at a stage outside the active ones, or once Airtable no longer has it, the next sync takes the account off the school. See [[A school came in through Airtable, not the site's form]].

### A signed Collaboration Agreement arrived

*When:* `Administrator Dashboard > Overview`, card **Collaboration Agreements**, shows the school under **Awaiting review**. The people in **Who is emailed about institutions**, or every Administrator, get "Signed agreement from {school} is waiting for review".

1. Open the **Collaboration Agreements** card and find the school under **Awaiting review**.
2. Press **Download the signed agreement** and open the file in your own PDF viewer.
3. Check it against the list under "Review the signed agreement from {school}".
4. If it is right, press **Accept it** and confirm.
5. If something must change, write it in the box (20 to 2000 characters) and press **Return it with this note**.

**What happens next**

- **Accept it** sets Agreement Status to Accepted in Airtable, and Current Stage to Confirmed when the stage was earlier. The school's account opens.
- Everybody at the school gets "Your agreement is accepted".
- **Return it with this note** emails your note to everybody at the school as you wrote it, with your address to reply to. Subject: "Your signed agreement needs a change". The school uploads a new copy.

**Good to know**

- **Accept it** is refused while Airtable's Current Stage is *Not Moving Forward*, *SPAM* or *Revisit Later*. Change the stage in Airtable first.
- A signed copy that came by email: open the school's Institution Dashboard, press **Add or re-add an account on the Institutions screen**, and use **Upload a signed agreement on the institution's behalf**. It lands under **Awaiting review** like the school's own.
- An agreement is marked *Overdue* after the days set in **Agreement review** (3 unless changed).

### Record an agreement the program already holds

*When:* the school signed before the site existed, and the program holds the signed copy. `Institutions > Agreements`, card **Agreements on file**, lists the *Confirmed* schools with no agreement recorded.

1. Open `WPCredits Program > Institutions`, tab **Agreements**, and press the school's name under **Agreements on file**. Its Institution Dashboard opens at the agreement.
2. Under **Record an agreement the program already holds**, paste the link in **Drive link to the folder or the file (required)**.
3. Fill in **Date signed (optional)** and **Where it is, in one line (optional)** if you know them.
4. Press **Record it** and confirm.

**What happens next**

- Agreement Status is set to *On file* in Airtable, and the school's account opens. The screen says "The agreement is recorded as on file. The institution's account is open."
- Current Stage in Airtable is not changed.

**Good to know**

- The link must be an https address on drive.google.com or docs.google.com.
- The form is there only while the school's agreement is not settled. It refuses a school that already has an accepted agreement.
- You can record one for any school this way, from its Institution Dashboard, not only those on the list.

### Take an agreement out of force, or put it back

*When:* a partnership ends or is paused, or an agreement was revoked by mistake.

1. To revoke: open `WPCredits Program > Institutions`, tab **Agreements**, card **Agreements in force**.
2. Under the school, open **Revoke this agreement**.
3. Write the reason in **Why it is revoked, in your own words** (20 to 2000 characters).
4. Press **Revoke this agreement** and confirm.
5. To put one back: open `WPCredits Program > Institutions`, tab **Agreements**, card **Revoked agreements**. It lists each school whose agreement was revoked, with the day and your note.
6. Press **Reinstate** next to the school and confirm.

**What happens next**

- Revoke: the school's people keep their accounts but see only the Collaboration Agreement card. Waiting invitations are canceled. Everybody there gets your note in "Your agreement has been revoked".
- Reinstate asks "Reinstate the agreement with {school}? It is back in force at once, and everybody at the institution is emailed." Once you confirm, the account opens again, and everybody at the school gets "Your agreement is in force again". You land back on the **Agreements** tab.

**Good to know**

- Revoking does not change Current Stage in Airtable. If the partnership has ended, change the stage there.
- **Reinstate** is refused when an accepted agreement already stands for the school. It works only on the school's most recently revoked agreement.
- **Revoked agreements** lists a school at any time while it has a revoked agreement and nothing accepted or waiting for review, also one that has since started a new agreement or had one returned. The Administrator Dashboard's **Collaboration Agreements** card also offers **Reinstate** while a signed agreement waits for review.
- A school at a stage outside the active ones is listed without **Reinstate**: reinstated, the next institutions sync would close it again. In its place the row says "At the stage "{stage}", which is outside the pipeline. Move it to an active stage in Airtable first: reinstated now, the next institutions sync would close it again." A school with no stage keeps the button.

### Assign a school to another Administrator, or to nobody

*When:* someone else should follow a school up, or it has *Nobody yet*.

1. Open the school's Institution Dashboard.
2. On the details card, find **Assigned Administrator**.
3. Open **Change who follows it up**.
4. In **Administrator**, type part of the name and pick the person, or pick **Nobody**.
5. Press **Save**.

**What happens next**

- The new Administrator gets "{school} is assigned to you", unless they made the change themselves. The screen says "The assigned Administrator is saved, and they have been emailed."
- The school sees that person under **Your contact at the program**, with **Book a call** when they have a booking link.

**Good to know**

- **Nobody** is a choice that stays. Approval and the sync never replace it.
- Only accounts that can manage the program are in the list. A person who loses that shows as "{name} (not an Administrator now)".
- New schools are assigned by country. The countries are set in **Areas of responsibility** on `Settings > Institutions`.

### A school asks to correct its details

*When:* `Administrator Dashboard > Overview`, card **Requests from institutions**, shows "{school} asks to correct its details". The people in **Who is emailed about institutions**, or every Administrator, are emailed.

1. Open the **Requests from institutions** card and find the request.
2. Read the table: **Detail**, **On record now** and **Asked for**.
3. To make the change, press **Approve and update the records**.
4. To refuse, write a note in **A note for the school (required to decline)** (20 to 2000 characters) and press **Decline**.

**What happens next**

- **Approve and update the records** writes Airtable first, then the site. The person who asked is emailed "Your institution's details are updated".
- **Decline**: the person who asked is emailed your note, and every account at the school reads it on the details card.

**Good to know**

- The request covers the school's name and address part: **Name**, **Website**, **City**, **Country** and **Contact's email**. The school saves its other details itself.
- If Airtable cannot be written, nothing changes and the request stays open.
- A request is marked *Overdue* after 14 days.

### A school's other requests (a student to add, a mentor wanted)

*When:* the **Requests from institutions** card shows *A student to add* or *A mentor is wanted*. A school's enrollment raises *A student to add* for each student it creates. For a request that says "{school} asks to correct its details", see [[A school asks to correct its details]].

1. Open the **Requests from institutions** card and find the request.
2. Read "The student is {name}. Raised by {who}."
3. Do the work in Airtable, such as assigning the student a mentor.
4. If you like, write what you did in the note box. It is for the log and is not sent to the school.
5. Press **Mark as handled**, or **Decline**.

**What happens next**

- The request closes and moves to **Recently closed**, as *Handled* or *Declined*.
- The school is not emailed.

**Good to know**

- A mentor assigned in Airtable reaches the site at the next students sync.
- Closing the row does nothing in Airtable. It only says the request is dealt with.

### Open a school's Institution Dashboard

*When:* you need to act for a school, or see what it sees.

1. In the WordPress toolbar, point to **Dashboards** and choose **Institution Dashboard**.
2. In **Viewing as institution**, type part of the school's name and pick it.
3. Press **Show**.

**What happens next**

- You see the school's whole page, even before its agreement is settled. A banner then says "This institution's agreement is not settled: {state}. Members see only the Collaboration Agreement card."
- A section **For Administrators** holds the school's **History**: what was done, when and by whom.

**Good to know**

- Other ways in: a school's name on the **Pipeline** tab, **Find an institution** with **Open its page** on the same tab, or a school's name on an Administrator Dashboard card. On the **Institution applications** card, a name opens the application on the Institutions screen instead.

### Correct a school's details yourself, and set the tracks it offers

*When:* a school's details are wrong, or it offers a new track.

1. Open the school's Institution Dashboard and find the details card.
2. Under **Contact and agreement** or **Name and address**, open **Correct these**.
3. Change the details and press **Save the details**.
4. For tracks: under **Tracks**, open **Change the tracks**.
5. Tick the tracks the school offers and press **Save tracks**.

**What happens next**

- Your changes are saved at once. Nobody approves them.
- The tracks screen says "The tracks offered are saved." Students are asked which track they want when two or more are ticked.

**Good to know**

- While a request from the school waits, **Name and address** cannot be changed here. Decide the request first. See [[A school asks to correct its details]].
- The school cannot change its tracks. It is told to write to the program.
- Changes to **Name and address** are written to Airtable. If Airtable cannot take them, nothing is saved and the card says so.

### Invite a representative or a tutor for a school, and keep its tutor list

*When:* a school asks you to add a colleague, or its tutor list needs a name.

1. Open the school's Institution Dashboard and find **Representatives and tutors**.
2. Under **Invite a colleague**, type the address in **Their email address**.
3. Under **Level**, choose **Representative** or **Tutor**.
4. Press **Send invitation**.
5. For the tutor list: under **Tutors**, in **Add a tutor**, type the **Name** and **Email**, then press **Add**.
6. To take a tutor off the list, press **Remove** beside them.

**What happens next**

- The person gets "An invitation to {school}". Its link is good for 14 days.
- The invitation cannot be accepted until the school's agreement is settled.
- A tutor on the list appears in the **Tutor** box when students are enrolled.

**Good to know**

- A Tutor sees the students, their Student Report Cards and the semester reports, and changes nothing.
- A school can have up to 10 invitations waiting. Each person can send 5 a day for one school.
- A listed tutor needs no account. Removing a tutor from the list keeps their account.

### Enroll students for a school

*When:* a school sends you its students. **Enrollment lists from institutions** ("Let an institution send a list of students to enroll") must be on in `Settings > Institutions`. It is off unless changed.

1. Open the school's Institution Dashboard and find **Enroll students**.
2. Choose the **Program** and the **Start date**, and the end date if you know it.
3. Tick the box that says the students have been notified.
4. Fill in **One student**, or give **Or a list** as a CSV file or pasted text.
5. Press **Check the list** and read what it says for each row.
6. Press **Create these student records**, or **Throw this list away**.

**What happens next**

- The student records are created. Nobody is emailed now.
- Each created student raises *A student to add* on the **Requests from institutions** card.
- A long list is created in parts. The page shows how many are done, and **Continue** carries on at once. It also carries on by itself.

**Good to know**

- Enrolling stops while the school's agreement is not settled, whoever presses.
- Do not send these students to the registration form. It would create a second record.
- The **Tutor** box lists the school's tutor list and can stay *Not recorded*. Add tutors first only if you want one chosen.

### A semester report: draft, review, approve, reopen

*When:* `Administrator Dashboard > Overview`, card **Semester reports**, lists drafts under **To review** or semesters under **Due for drafting**.

1. For a semester under **Due for drafting**, press **Draft now**.
2. For any other semester: open `Institutions > Semester reports`, under **Draft any semester** pick the **Institution** and the semester, and press **Draft now**.
3. Under **To review**, press **Review** on the draft. It opens on the school's Institution Dashboard.
4. Write what you want in the sections and press **Save the report**.
5. Press **Approve this report**.
6. To change an approved report, press **Reopen for editing**.

**What happens next**

- A new draft says "The draft is ready for review." The school cannot see it.
- **Approve this report**: the school can read and download it, and its accounts are emailed. With no account the screen says "send the PDF by hand".
- **Reopen for editing** makes it a draft again, and the school no longer sees it.

**Good to know**

- While **Drafting** is on in Settings, the site also drafts reports by itself after a semester has ended.
- The school sees only approved reports.
- **Draft now** opens the new draft at once. If the semester already has a report, it opens that one. It is refused when the school has no students in that semester, and after 20 drafts by one Administrator in a day.

### Find an application or agreement past the fifty the dashboard lists

*When:* the **Institution applications** card says "N more are waiting; they are listed here as these are decided." The steps are for applications: agreements have no such line.

1. Decide the oldest applications. The next ones move onto the card as you do.
2. To decide a newer one now, open the link in the email about it. It opens the application on the Institutions screen.
3. Read "The Administrator Dashboard's card lists the 50 oldest open applications, and this one is past them, so it is decided here."
4. Use the same buttons as on the card: **Approve**, **Send this question**, **Reject** or **Reject as spam**.

**What happens next**

- Each press comes back to the Institutions screen with the result above the tabs.

**Good to know**

- The **Waiting for review** tab also lists only the 50 oldest items.
- **Awaiting review** on the **Collaboration Agreements** card lists up to 200 agreements, and the **Requests from institutions** card up to 200 requests. Neither says when more wait. The folds of returned and revoked agreements list 50 each.
