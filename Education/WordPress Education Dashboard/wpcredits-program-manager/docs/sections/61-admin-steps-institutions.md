## Step by step: institutions

### From application to first students: the whole path

*When:* you want to know what comes next for a school, and who does it.

**Approve** is not a spam check. It creates the school's record and its first account, so press it only when the program has decided to take the school.

1. The school sends the application form on the site. Detail: [[A school applied]].
2. The school follows the link in its acknowledgement email to confirm its address. Detail: [[The application's address is not confirmed]].
3. You talk with the school. The acknowledgement email gives them a link to book a call with you, when you have a booking link.
4. You press **Approve**. The site creates the Airtable record and the account, and emails a password link. Once the site keeps the record of institutions, it also adds the school to the site's list, where its talks are kept. Detail: [[A school applied]].
5. The school sets its password, signs the Collaboration Agreement and uploads it. Detail: [[A signed Collaboration Agreement arrived]].
6. You press **Accept it**. The site opens the school's account and sets Current Stage to Confirmed in Airtable. Once the site keeps the record, the stage on the school's Talks card moves to Confirmed too. Detail: [[A signed Collaboration Agreement arrived]].
7. The school, or you, enrolls students, if enrolling by list is on. Detail: [[Enroll students for a school]].
8. After the semester the site drafts the semester report, if **Drafting** is on, and you approve it. Detail: [[A semester report: draft, review, approve, reopen]].

**Good to know**

- The booking link in step 3 is the **Booking link** on the assigned Administrator's own WordPress profile. With nobody assigned, it is the country contact's link as it stood when the school applied. With no link, the email leaves the call out.
- A school that came in through the old Airtable form skips steps 1 to 4. Detail: [[A school came in through Airtable, not the site's form]].
- Once the site keeps the record of institutions, step 3 is kept on the school's Talks card: its stage, next step and follow-up date, and your notes. Detail: [[Change a school's stage, next step and follow-up date]].

### A school applied

{{clip:a-school-applied}}

*When:* the school is on `Administrator Dashboard > Overview`, card **Institution applications**. You may also get an email "{school} is assigned to you" or "New institution application from {school}".

1. Open `Administrator Dashboard > Overview` and find the school on the **Institution applications** card.
2. Press **Read the application** and read the answers.
3. To ask the applicant something, press **Ask a question**, type it in **Ask the applicant something** and press **Send this question**. The question must be 10 to 2000 characters.
4. Once the site keeps the record of institutions, press the school's name on the card. The application opens on `WPCredits Program > Institutions`. Read **What the site already has**.
5. If it names a lead, reopen the lead if it is closed, then move it into the talks first, so its notes come with it, then approve. See [[Close a lead, or reopen it]] and [[Move a lead into the talks as an institution]]. When it says the lead's notes do not move by themselves, because an institution matched already, copy what matters from the lead's notes to the institution's Talks card instead.
6. Once you have talked with the school and the program wants to take it, press **Approve** and confirm.
7. If the program will not take it, press **Reject**, type a reason in **Why, for the next Administrator who reads this** if you like, then press **Reject the application** and confirm.
8. If it is not a real application, press **Reject as spam** and confirm.

**What happens next**

- **Approve** creates the school in Airtable, at the stage set in **Institution starting stage** (*First Contact Made* unless changed), with Agreement Status *Not started*. If Airtable already holds the school by name or contact address, that record is used instead. It keeps its own stage and Agreement Status.
- Once the site keeps the record, the school of the same name or address is looked for in the site's list instead, and Airtable is not asked.
- It also makes the school's first account from the contact's address, and queues the email "Your institution account is ready". It tells them to set a password and then do the Collaboration Agreement.
- **Send this question** emails your words as written, with your address to reply to. The state becomes *waiting on the applicant*, and **Put back in the queue** appears on the application: it puts the application back in the queue as a new one.
- **Reject the application** sends the applicant "About your application to the WordPress Credits Program", with no reason in it. Your reason stays on the site.
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

*When:* **Approve** said "Nothing was finished", or the application says it "stopped partway" or carries the mark *approval stopped partway*.

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

*When:* while Airtable keeps the record of institutions, the school applied through the old Airtable form. It is on `WPCredits Program > Institutions`, tab **Pipeline**, and while its Current Stage is empty it is under **Not counted above**, in *No stage*. It is not on **Institution applications**.

Once the site keeps the record, the sync no longer reads the Institutions table, so a school entered only in Airtable never reaches the site. Add it as a lead instead. See [[Add a lead]].

1. In Airtable, set the school's Current Stage to a stage listed in **Institution pipeline stages** on `Settings > Advanced`.
2. Open `WPCredits Program > Institutions`, tab **Sync and storage**, and press **Sync institutions now**. The sync also runs by itself every three hours.
3. Open the **Pipeline** tab, find the school under its stage, and press its name. Its Institution Dashboard opens on its **Overview**.
4. Open **Your institution** and read **Assigned Administrator** on the details card. If it says *Nobody yet*, see [[Assign a school to another Administrator, or to nobody]].
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
2. Open **Your institution**. In **Representatives and tutors**, press **Add or re-add an account on the Institutions screen**.
3. Under **Add an account**, type the person's **Name** and **Email address**.
4. Press **Add account**.

**What happens next**

- The site makes the account and joins it to the school. The screen says "The account was added and an invitation is queued."
- The person gets "Your institution account is ready". Before the agreement is settled it tells them to do the agreement. After, it says the account is open.

**Good to know**

- An address that already has an account is refused, unless it is a former member of this school or a mentor's account.
- For a school at *Confirmed* with its agreement settled, `Institutions > Accounts`, view **No account**, shows **Create account** on its row. It uses Airtable's Contact Email. Once the site keeps the record, it uses the institution's contact email, which you correct in **Your institution's details** on its Institution Dashboard. A row that is not *Ready* says why, for example when the school has had a member before or the address already has an account.
- A school with no stage is left alone, but give it an active stage first, in Airtable, or on its Talks card once the site keeps the record: at a stage outside the active ones, or once Airtable no longer has it, the next sync takes the account off the school. See [[A school came in through Airtable, not the site's form]].

### A signed Collaboration Agreement arrived

{{clip:a-signed-agreement-arrived}}

*When:* `Administrator Dashboard > Overview`, card **Collaboration Agreements**, shows the school tagged **Awaiting review**. The people in **Who is emailed about institutions**, or every Administrator, get "Signed agreement from {school} is waiting for review".

1. Open the **Collaboration Agreements** card and find the school tagged **Awaiting review**.
2. Press **Download the signed agreement** and open the file in your own PDF viewer.
3. Check it against the list under "Review the signed agreement from {school}".
4. If it is right, press **Accept it** and confirm.
5. If something must change, press **Return with a note**, write it in **What has to change** (20 to 2000 characters) and press **Return it with this note**.

**What happens next**

- **Accept it** sets Agreement Status to Accepted in Airtable, and Current Stage to Confirmed when the stage was earlier. The school's account opens.
- Everybody at the school gets "Your agreement is accepted".
- **Return it with this note** emails your note to everybody at the school as you wrote it, with your address to reply to. Subject: "Your signed agreement needs a change". The school uploads a new copy.

**Good to know**

- **Accept it** is refused while Airtable's Current Stage is *Not Moving Forward*, *SPAM* or *Revisit Later*. Change the stage in Airtable first.
- Once the site keeps the record, it is the stage on the school's Talks card that counts, and **Accept it** moves that stage to *Confirmed* too. The refusal then reads "Nothing was accepted. This institution's stage says the program is not going ahead with it. Change the stage on its Talks card first: ...". See [[Change a school's stage, next step and follow-up date]].
- A signed copy that came by email: open the school's Institution Dashboard, open **Your institution**, press **Add or re-add an account on the Institutions screen**, and use **Upload a signed agreement on the institution's behalf**. It lands on the card, tagged **Awaiting review**, like the school's own.
- An agreement is marked *Overdue* after the days set in **Agreement review** (3 unless changed).

### Record an agreement the program already holds

*When:* the school signed before the site existed, and the program holds the signed copy. `Institutions > Agreements`, card **Agreements on file**, lists the *Confirmed* schools with no agreement recorded.

1. Open `WPCredits Program > Institutions`, tab **Agreements**, and press the school's name under **Agreements on file**. Its Institution Dashboard opens on **Agreement**.
2. Under **Record an agreement the program already holds**, paste the link in **Drive link to the folder or the file**. It is required.
3. Fill in **Date signed** and **Where it is, in one line** if you know them. Both are optional.
4. Press **Record it** and confirm.

**What happens next**

- Agreement Status is set to *On file* in Airtable, and the school's account opens. The screen says "The agreement is recorded as on file. The institution's account is open."
- While Airtable keeps the record, Current Stage is not changed. Once the site keeps the record, the stage moves to *Confirmed* when it was earlier, on the site and in Airtable.

**Good to know**

- The link must be an https address on drive.google.com or docs.google.com.
- The form is there only while the school's agreement is not settled. It refuses a school that already has an accepted agreement.
- You can record one for any school this way, from the **Agreement** view of its Institution Dashboard, not only those on the list.
- Once the site keeps the record, it is refused while the talks are at *Revisit Later*, *Not Moving Forward* or *SPAM*: "Nothing was recorded: the talks with this institution are at Revisit Later. Move it to a stage where talks go on, then record the agreement."

### Take an agreement out of force, or put it back

*When:* a partnership ends or is paused, or an agreement was revoked by mistake.

1. To revoke: open `WPCredits Program > Institutions`, tab **Agreements**, card **Agreements in force**.
2. Under the school, open **Revoke this agreement**.
3. Write the reason in **Why it is revoked, in your own words** (20 to 2000 characters).
4. Press **Revoke this agreement** and confirm.
5. To put one back: open `WPCredits Program > Institutions`, tab **Agreements**, card **Revoked agreements**. It lists each school whose agreement was revoked, with the day and your note.
6. Press **Reinstate** next to the school and confirm.

**What happens next**

- Revoke: the school's people keep their accounts. Since 1.123.96 its representatives reach only **Overview** and **Agreement**, and its tutors no page. Waiting invitations are canceled. Everybody there gets your note in "Your agreement has been revoked".
- Reinstate asks "Reinstate the agreement with {school}? It is back in force at once, and everybody at the institution is emailed." Once you confirm, the account opens again, and everybody at the school gets "Your agreement is in force again". You land back on the **Agreements** tab.

**Good to know**

- Revoking does not change Current Stage in Airtable. If the partnership has ended, change the stage there. Once the site keeps the record, change it on the school's Talks card.
- **Reinstate** is refused when an accepted agreement already stands for the school. It works only on the school's most recently revoked agreement.
- **Revoked agreements** lists a school at any time while it has a revoked agreement and nothing accepted or waiting for review, also one that has since started a new agreement or had one returned. The Administrator Dashboard's **Collaboration Agreements** card also offers **Reinstate** while a signed agreement waits for review.
- A school at a stage outside the active ones is listed without **Reinstate**: reinstated, the next institutions sync would close it again. In its place the row says "At the stage "{stage}", which is outside the pipeline. Move it to an active stage in Airtable first: reinstated now, the next institutions sync would close it again." A school with no stage keeps the button. Once the site keeps the record, the line reads "Move it to an active stage on its Talks card first".
- Since 1.123.102 the same stage rule holds everywhere. On the Administrator Dashboard's **Collaboration Agreements** card such a school's row has no **Reinstate**, and says "At the stage "{stage}". Reinstate after it moves to an active stage in Airtable." Once the site keeps the record, it says "Reinstate after it moves to an active stage on its Talks card."
- A press of **Reinstate** for such a school, from a page drawn before its stage changed, is refused with "Nothing was reinstated. The institution's stage is outside the pipeline. Move it to an active stage in Airtable first: reinstated now, the next institutions sync would close it again." Nothing is written and nobody is emailed. Once the site keeps the record, it reads "on its Talks card first".
- A school no longer in the site's list of institutions is not reinstated either: the press says "Nothing was reinstated. That institution is not in the site's list of institutions any more.", and its row on the Administrator Dashboard has no **Reinstate**.

### Assign a school to another Administrator, or to nobody

*When:* someone else should follow a school up, or it has *Nobody yet*.

1. Open the school's Institution Dashboard.
2. While Airtable keeps the record of institutions: open **Your institution**, find **Assigned Administrator** on the details card and open **Change who follows it up**. Then type part of the name in **Administrator**.
3. Once the site keeps the record: open **For Administrators**. On the Talks card, type part of the name in **Assigned Administrator**. For a lead, open its page from `Administrator Dashboard > Talks`.
4. Pick the person, or pick **Nobody**.
5. Press **Save**.

**What happens next**

- The new Administrator gets "{school} is assigned to you", unless they made the change themselves. The details card says "The assigned Administrator is saved, and they have been emailed." The Talks card says "The talks are saved, and the assigned Administrator has been emailed."
- The school sees that person under **Your contact at the program**, with **Book a call** when they have a booking link.

**Good to know**

- Once the site keeps the record, the details card has no **Assigned Administrator**: the Talks card holds it.
- **Nobody** is a choice that stays. Approval and the sync never replace it.
- Only accounts that can manage the program are in the list. A person who loses that shows as "{name} (not an Administrator now)".
- New schools are assigned by country. The countries are set in **Areas of responsibility** on `Settings > Institutions`.

### Read the follow-ups due and open a school's talks

{{clip:read-the-follow-ups-due}}

*When:* you want to know which schools to follow up today. **Talks** in the Administrator Dashboard's menu has an amber number when some are due. It is in the menu once the site keeps the record of institutions.

1. Open `Administrator Dashboard > Talks`.
2. Read the head. It says how many follow-ups are due, such as "3 follow-ups are due", and which one is the oldest.
3. Read the table **Follow-ups due**: **School**, **Next step** and **Follow up on**, oldest first.
4. Read the schools by stage under it, from Lead to Confirmed, each stage with its count, such as "Agreement Sent: 3 schools".
5. To see every Administrator's schools, press **Show all schools**. **Show your schools** goes back.
6. Press a school's name to open its Talks card.

**What happens next**

- A school's name opens its Institution Dashboard on **For Administrators**, at the Talks card. A lead's name opens the lead's own page in the Talks view.

**Good to know**

- A follow-up is due when its date is today or earlier, while the talks go on. See [[The words the screens use]].
- The view opens on your own schools: "Showing your schools". Your schools are the ones assigned to you, the ones assigned to nobody in your area, and the ones assigned to nobody that no Administrator's area holds. Every Administrator has those last ones.
- The number beside **Talks** in the menu counts your own follow-ups due. It is gone when none is due.
- Revisit Later, Not Moving Forward, SPAM and any stage the site does not know are folded at the end.
- A school at Student is in no list: **Find a school** finds it.
- A stage with more than 20 schools shows 20, and a link to show all of them.
- A school assigned to someone who is no longer an Administrator counts as assigned to nobody, and is tagged **Not assigned**.
- What the view shows, part by part: [[The Talks view]].

### Change a school's stage, next step and follow-up date

{{clip:change-a-schools-stage}}

*When:* a call or an email moved the talks with a school on, or you did a follow-up.

1. Open the school's Talks card: press its name in `Administrator Dashboard > Talks`.
2. Change what moved on:
   - **Stage**
   - **Next step**
   - **Follow up on**
   - **Last contact**
   - any other field: **Assigned Administrator**, **Country**, **Help desk link**, **Estimated students**, **First students begin** or **Program details**
3. Press **Save**.
4. If the card says "Nothing was saved: these talks changed after you opened the card.", read the values it shows now, make your change again and press **Save**.

**What happens next**

- The card says "The talks are saved." Under **Stage**, a line says when the stage last changed and who changed it: "Since {day}, by {name}."
- What changed is copied to Airtable, where Airtable has a column for it. See [[Airtable's copy of the record]].
- The school's **History** gets a row for the change.

**Good to know**

- The Talks card is also the first card under **For Administrators** on the school's Institution Dashboard.
- Moving to Revisit Later sets **Follow up on** 90 days ahead, when you left the date as it was. The card says so, and you can change it.
- The tag **Past due** beside the name means the follow-up date is before today.
- **Last contact** cannot be a day after today: "Last contact cannot be after today."
- **Help desk link** takes an address that starts with https://: "The help desk link must be an address that starts with https://." When it holds one, **Open the help desk link** under the box opens it.
- The contact person and their email are changed on **Your institution's details**, on the **Your institution** view. The card shows them under **On record**.
- Lead is never offered to a school that already has a record: "Lead is only for a school the program has not talked to yet."
- When two people save the same school at once, one is told "Someone else is changing these talks just now, so nothing was saved. Try again in a moment."
- If the school is gone from the site's list meanwhile, the card says "That school is not in the talks any more." Look for it with **Find a school** on the Talks view.
- Every field, what it holds and its limits: [[The Talks card]].

### A school leaves the active stages, and comes back

*When:* the program stops the talks with a school, at Revisit Later, Not Moving Forward or SPAM, or takes them up again.

1. On the school's Talks card, choose the new **Stage** and press **Save**.
2. Read the question, such as "Moving {school} to Not Moving Forward removes the access of its 2 accounts. Save again to confirm."
3. Press **Save** again to confirm. Choosing another stage asks again.
4. To take the talks up again, choose a stage that is ticked in **Institution pipeline stages** and press **Save**.

**What happens next**

- On leaving, the site takes away the school's agreement record at once, as a sync takes it away. With **Its people** at its default, its accounts lose their access: "The access of its 2 accounts was removed." Otherwise the card says "Its Institution Dashboard is locked now."
- On coming back, a school whose agreement is settled opens again: "Its Institution Dashboard is open again." If the site cannot open it yet, the card says "Its Institution Dashboard is still locked. It opens again with the next sync."
- Accounts whose access the move out removed do not come back by themselves, and the card says so: "The account removed when it left does not come back by itself: add it again with Re-add, under Former members on Manage members.", or for more, such as "The 2 accounts removed when it left do not come back by themselves: add them again with Re-add, under Former members on Manage members." Add them again under **Former members** on **Manage members**, with **Re-add**.
- The card counts only the accounts the move out removed and that belong to no other school now. Someone an Administrator removed on purpose, or who left, is not counted.

**Good to know**

- The active stages are the ones ticked in **Institution pipeline stages** on `Settings > Advanced`. Revisit Later, Not Moving Forward and SPAM are not ticked by default.
- A school at Revisit Later still has its follow-up counted as due. Not Moving Forward and SPAM are never due.
- In the Talks view the three are folded at the end.

### Confirmed and Student come from the agreement

*When:* the **Stage** box does not offer Confirmed, or offers only Confirmed, Student and the closed stages.

1. Open the school's Institution Dashboard, view **Agreement**, and read where its Collaboration Agreement stands.
2. Before the agreement is settled, the Talks card offers First Contact Made to Agreement Sent, Revisit Later, Not Moving Forward and SPAM. To reach Confirmed, accept the signed agreement or record one on file. See [[A signed Collaboration Agreement arrived]] and [[Record an agreement the program already holds]].
3. Once the agreement is settled, the card offers Confirmed, Student, Revisit Later, Not Moving Forward and SPAM. Choose the stage and press **Save**.

**What happens next**

- Accepting the agreement, or recording it on file, moves the stage to Confirmed when it was earlier.

**Good to know**

- A stage the list does not offer is refused: "Confirmed is set when the Collaboration Agreement is accepted or recorded as on file." or "This institution's Collaboration Agreement is settled, so its talks are at Confirmed or Student."
- The school's own stage always stays in the list, so a save that does not touch it keeps it.
- A test institution keeps its stage when its agreement is accepted.

### Record what a school asks for

*When:* a school tells you what it needs from the program, such as hours, dates or reports.

1. Open the school's Talks card, or a lead's page.
2. Type what it asks for in **Program details**.
3. Press **Save**.

**What happens next**

- It is kept with the school's talks, line breaks and all. Only Administrators see it.

**Good to know**

- It never goes to the school and never to Airtable. The Talks view, **Follow-ups due** and the Monday email do not show it.
- It takes 5,000 characters at most: "Program details can be at most 5000 characters long."
- A lead's **Program details** go with it when it moves into the talks as an institution.

### Add a note to a school's talks

{{clip:add-a-note-to-a-schools-talks}}

*When:* something was said or done with a school that the next Administrator should know.

1. Open the school's Talks card, or a lead's page.
2. Under **Notes**, type in **Add a note**. A note takes 5,000 characters at most.
3. Press **Add the note**.

**What happens next**

- The card says "The note is added." The note is first under **Notes**, with your name and the moment.

**Good to know**

- A note cannot be changed or removed on the card.
- Notes never go to the school or to Airtable.
- A second press of the same words says "That note is already added." and adds nothing.
- A note for a lead that has become an institution is refused: "This lead is an institution now. Add the note on its Talks card." Open the school's Talks card from the lead's page and add it there.
- Where the notes stand on the card: [[The Talks card]].

### Add a lead

{{clip:add-a-lead}}

*When:* you heard of a school the program has not talked to yet, and it should be followed up.

1. Open `Administrator Dashboard > Talks`.
2. Press **Add a lead**.
3. Type the school's **Name**. It is required.
4. Fill in what you know:
   - **City** and **Country**
   - **Contact person** and **Contact's email**
   - **Help desk link**
   - **Next step** and **Follow up on**
   - a **Note**
5. Press **Add the lead**.

**What happens next**

- The lead's page opens with "The lead is added." It is at the stage Lead, in the Talks view's first list.
- It is assigned by its country's area, and that Administrator is emailed, unless it is you.

**Good to know**

- A lead is never written to Airtable.
- **Help desk link** must start with https://.
- A name already in the talks, a school's or a lead's, is refused: "A school of that name is already in the talks."
- If its note is refused, the lead is still added: "The lead is added, but its note is not. Correct the note below and add it again."
- While another lead is being added, the add is refused: "Another lead is being saved just now. Try again in a moment." Press **Add the lead** again in a moment.
- What a lead is, and what it holds: [[Leads]].

### Close a lead, or reopen it

*When:* a lead goes nowhere, or comes back.

1. Open the lead's page: press its name in `Administrator Dashboard > Talks`, or find it with **Find a school**.
2. Press **Close this lead**.
3. To bring it back, press **Reopen this lead**.

**What happens next**

- "The lead is closed." It keeps everything and is tagged **Closed**. It is left out of the stage lists, **Follow-ups due** and the Monday email. **Find a school** still finds it.
- "The lead is reopened." It is back at Lead, in the lists.

**Good to know**

- A lead that goes nowhere is closed, never moved to Revisit Later, Not Moving Forward or SPAM. The card refuses those for a lead: "A lead that goes nowhere is closed, not moved to SPAM."
- A closed lead's **Stage** offers only Lead. Reopen it before you move it on.
- What you typed and did not save stays in the form: "The lead is closed. What you typed is not saved yet: press Save to keep it."
- A lead whose move into the talks did not finish cannot be closed. See [[Move a lead into the talks as an institution]].
- A press on a page drawn before the lead was deleted, or before it became an institution, says "That lead is not in the talks any more." Look for it with **Find a school**.

### Move a lead into the talks as an institution

{{clip:move-a-lead-into-the-talks}}

*When:* you have talked with a lead, and the program wants to go on with it as a school.

1. Open the lead's page.
2. If **Country** is empty, choose the school's country from the list.
3. In **Stage**, choose where the talks are: First Contact Made to Agreement Sent.
4. Press **Save**.
5. If the card then says "This lead's move into the talks as an institution did not finish. Choose the stage again and save to finish it.", choose the stage again and press **Save**.

**What happens next**

- The site creates the institution's record in Airtable and adds it to the site's list. The lead's fields, notes and history go with it.
- The card says "The lead is an institution now. Its talks go on here." It is the school's own Talks card now.
- Its fields are copied to the new record where Airtable has a column for them. See [[Airtable's copy of the record]].
- The lead's old page says "This lead is an institution now: {school}.", with a link to its Talks card.

**Good to know**

- Confirmed and Student are refused: "Confirmed is set when the Collaboration Agreement is accepted or recorded as on file."
- A closed lead is refused: "This lead is closed. Reopen it before you move it on."
- A country kept only as text is refused: "The country of this lead, {country}, is not one of the program's yet. Choose it, then move the lead on."
- If Airtable cannot create the record: "Airtable could not create the institution's record, so it is still a lead. Try again in a while."
- A lead whose move did not finish cannot be closed or deleted until the move is finished.
- What a lead is, and what it holds: [[Leads]].

### Delete a lead

*When:* a closed lead should go from the talks.

1. Close the lead first. See [[Close a lead, or reopen it]].
2. On its page, press **Delete this lead**.
3. Read the question, such as "Deleting removes {name}, its contact and its 2 notes from the talks. Press Delete this lead again to confirm."
4. Press **Delete this lead** again.

**What happens next**

- The lead and its notes go to WordPress's Trash. The Talks view opens and says "The lead {name} was deleted."
- Its name is free again for a new lead or school.

**Good to know**

- An open lead is refused: "Close this lead before you delete it."
- If the lead changes between the two presses, the question is asked again.
- A site that keeps no Trash refuses it: "This site keeps no Trash, so a lead cannot be deleted here."
- There is no screen to bring a deleted lead back. Until WordPress empties its Trash, the product owner can have it brought back whole.
- The deleted lead's old address says "This lead is not in the talks." There is nothing more to do.

### Find a school in the talks

*When:* you look for one school or lead, at any stage.

1. In `Administrator Dashboard > Talks`, type part of the name in **Find a school**.
2. Press **Find**.
3. Press a name to open its talks. **Clear** brings the lists back.

**What happens next**

- The heading says how many match, such as "2 schools in all the talks match that name". Each row starts with its stage.

**Good to know**

- Find looks at every school and lead, at every stage, closed leads too, whoever is assigned, whatever **Show all schools** says.
- Capitals and accents do not matter.
- With nothing found: "No school matches that name."

### The Monday email of follow-ups due arrived

*When:* you got "[{site}] Follow-ups due this week: {count}".

1. Read "These follow-ups are due, oldest first:": your own schools.
2. Read "Not assigned to anybody:": the schools in your list that nobody is assigned to.
3. Press a school's link to open its Talks card, or the link after "Open the Talks view:".
4. Do the follow-up. Then change **Next step** and **Follow up on** on the school's card, and press **Save**.

**What happens next**

- A school whose new date is after today is no longer due.

**Good to know**

- It goes once a week, only while the site keeps the record of institutions. When it is sent, and to whom: [[The Monday email of follow-ups due]].
- It goes to each Administrator with an area or an assigned school who has a follow-up due among their own schools. An Administrator with none gets no email.
- It lists the same follow-ups as **Follow-ups due** in the Talks view, while the view shows your schools.
- A Monday whose run only happens on Tuesday sends nothing that week.
- Its words are changed in `Tools > Emails`: **Institution follow-ups due this week, to the Administrator**. See [[Change the words of an email and test it]].

### A school asks to correct its details

*When:* `Administrator Dashboard > Overview`, card **Requests from institutions**, shows "{school} asks to correct its details". The people in **Who is emailed about institutions**, or every Administrator, are emailed.

1. Open the **Requests from institutions** card and find the request.
2. Read the table: **Detail**, **On record now** and **Asked for**.
3. To make the change, press **Approve and update the records**.
4. To refuse, press **Decline**, write a note in **A note for the school** (20 to 2000 characters) and press **Decline the request**.

**What happens next**

- **Approve and update the records** writes Airtable first, then the site. Once the site keeps the record, it writes the site first, and Airtable gets a copy. The person who asked is emailed "Your institution's details are updated".
- **Decline the request**: the person who asked is emailed your note, and every account at the school reads it on the details card.

**Good to know**

- The request covers the school's name and address part: **Name**, **Website**, **City**, **Country** and **Contact's email**. The school saves its other details itself.
- If the first write fails, nothing changes and the request stays open.
- A request is marked *Overdue* after 14 days.

### A school's other requests (a student to add, a mentor wanted)

{{clip:a-schools-other-requests}}

*When:* the **Requests from institutions** card shows *A student to add* or *A mentor is wanted*. A school's enrollment raises *A student to add* for each student it creates. For a request that says "{school} asks to correct its details", see [[A school asks to correct its details]].

1. Open the **Requests from institutions** card and find the request.
2. Read "The student is {name}. Raised by {who}."
3. Do the work in Airtable, such as assigning the student a mentor.
4. Press **Mark as handled**, or **Decline**.
5. If you like, write what you did in **What you did about it**. It is for the log and is not sent to the school.
6. Press **Mark the request as handled**, or **Decline the request**.

**What happens next**

- The request closes and moves to **Recently closed**, as *Handled* or *Declined*.
- The school is not emailed.

**Good to know**

- A mentor assigned in Airtable reaches the site at the next students sync.
- Closing the row does nothing in Airtable. It only says the request is dealt with.

### Open a school's Institution Dashboard

*When:* you need to act for a school, or see what it sees.

1. In the WordPress toolbar, point to **Dashboards** and choose **Institution Dashboard**. While you belong to no school and two or more schools exist, the page asks which school: its head reads **Choose an institution**.
2. In **Viewing as institution**, type part of the school's name and pick it. The school's page opens on its **Overview**.

**What happens next**

- You see every view the school's representatives see but **Events**, even before its agreement is settled.
- While it is not settled, the **Overview** has the row *The agreement is not settled*, saying "This institution's agreement is not settled: {state}. Its representatives see only the Overview and the Agreement, and its tutors no page." It is tagged **Waits** when you are the one to act, and **With the school** otherwise. **Agreement** opens with the school's own sentence about its agreement, so the note above it says only what that sentence does not. For a generated template, a copy waiting for review or a returned copy, the note reads "This institution's agreement is not settled. Its representatives see only the Overview and the Agreement, and its tutors no page." With no agreement, or a revoked one, the school's sentence already says both, and the note holds only what Airtable records, if anything. When this site and Airtable disagree, the note reads as on the **Overview**. When Airtable records an Agreement Status, "Airtable records the Agreement Status as {status}." follows the note.
- **For Administrators**, the last item in the menu, holds the school's **History**: what was done, when and by whom. Once the site keeps the record of institutions, its first card is the school's Talks card.
- **Viewing as institution** stays at the end of the page's first line on every view. Picking another school keeps the view you are on.

**Good to know**

- Since 1.123.96 the page asks for a school before it draws one, while you belong to no school and two or more schools exist, and does not remember the choice: an address that names no school asks again. With JavaScript off, press **Show** after picking.
- Other ways in: a school's name on the **Pipeline** tab, **Find an institution** with **Open its page** on the same tab, or a school's name on an Administrator Dashboard card. On the **Institution applications** card, a name opens the application on the Institutions screen instead.

### Correct a school's details yourself, and set the tracks it offers

*When:* a school's details are wrong, or it offers a new track.

1. Open the school's Institution Dashboard, then **Your institution**. The details card comes first.
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
- While Airtable keeps the record, changes to **Name and address** are written to Airtable first. If Airtable cannot take them, nothing is saved and the card says so.
- Once the site keeps the record, the site saves them first and Airtable gets a copy. If the site cannot save them: "The change could not be saved on the site, so nothing was changed. Try again in a while." A copy Airtable has not taken yet is on the notice of [[Read the notice about Airtable's copy]].

### Invite a representative or a tutor for a school, and keep its tutor list

{{clip:invite-a-representative}}

*When:* a school asks you to add a colleague, or its tutor list needs a name.

1. Open the school's Institution Dashboard, then **Your institution**, and find **Representatives and tutors**.
2. Open **Invite a colleague** and type the address in **Email address**.
3. Under **Level**, choose **Representative** or **Tutor**.
4. Press **Send invitation**.
5. For the tutor list: under **Tutors**, open **Add a tutor**, type the **Name** and **Email**, then press **Add**.
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

1. Open the school's Institution Dashboard, then **Enroll students** in its menu, or press **Enroll students** on its **Overview**.
2. Choose the **Program** and the **Start date**, and the **End date** if you know it.
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
3. Under **To review**, press **Review** on the draft. It opens on the school's Institution Dashboard, in its **Semester reports** view.
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
4. Decide with **Approve**, **Send this question**, **Reject** or **Reject as spam**. Here each box is already open above its button.

**What happens next**

- Each press comes back to the Institutions screen with the result above the tabs.

**Good to know**

- The **Waiting for review** tab also lists only the 50 oldest items.
- The **Collaboration Agreements** card lists up to 200 agreements awaiting review, and the **Requests from institutions** card up to 200 requests. Neither says when more wait. The folds of returned and revoked agreements list 50 each.
