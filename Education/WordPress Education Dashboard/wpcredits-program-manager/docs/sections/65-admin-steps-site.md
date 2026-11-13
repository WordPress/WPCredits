## Step by step: running the site

These jobs are done in the WordPress admin area, under `WPCredits Program`. They are for whoever looks after the site's settings, and most of them are done once or rarely.

### Run a sync by hand and read its report

*When:* you changed something in Airtable and want the site to see it now, without waiting for the next automatic run.

1. Open `WPCredits Program > Students`, tab **Sync**. For mentors, open `WPCredits Program > Mentors`, tab **Sync**.
2. Press **Sync students now** (or **Sync mentors now**).
3. Wait. The **Airtable sync** card shows each step and a progress bar while the run goes on.
4. When the run ends, the page reloads and says "Sync finished. Accounts created: ... Accounts refreshed: ...".
5. Read the **Last sync report** card under it: the counts, then any **Warnings**. If the run ended while the page was closed, there is no sentence: read the card.

**What happens next**

- New accounts are created, and existing ones are refreshed from Airtable.
- If **Invitation emails** is on (`Settings`, tab **Students and mentors**), new accounts are sent their invitation.
- For institutions, the button is **Sync institutions now**, on `WPCredits Program > Institutions`, tab **Sync and storage**.

**Good to know**

- The syncs also run by themselves every three hours while **Automatic sync** is ticked ("Read Airtable on a schedule", tab **Students and mentors**). The mentors run starts half an hour after the students run.
- A press while a run is going starts nothing. It says "A sync is already running. Its progress is shown below."
- "No progress for over two minutes" means the run may have stopped. Press **Cancel sync**, then start it again.

### A sync stopped before taking roles

*When:* the **Sync** tab shows **Last sync error:** with a sentence such as "Airtable returned no current students." and a **Run anyway** button.

1. If the page was open when the run stopped, it shows the sentence and says "Reload this page to choose Run anyway or Cancel." Reload the page.
2. Read the sentence. It says how many records Airtable returned, and how many accounts the site has linked.
3. Check in Airtable why so few came back: a status renamed, a status deleted, a view or column changed.
4. If Airtable was wrong, put it right there, then press **Sync students now** (or **Sync mentors now**) to start a new run.
5. If Airtable is right and the program really is that small now, press **Run anyway** and confirm the question.

**What happens next**

- While the run is stopped, every account keeps its role, and the automatic runs start nothing. Nothing happens until you choose.
- **Run anyway** goes on from where the run stopped. Every linked account the run did not read is marked as no longer on the program.
- If **When a student leaves the program** (or **When a mentor is no longer active**) is set to remove the role, which is the default, those accounts lose the Student role (or the Mentor role), except Administrator accounts. With the other choice they keep it. Both settings are on `Settings`, tab **Students and mentors**, section **When someone leaves**.
- The next run is checked again from the start.

**Good to know**

- After the reload, the screen offers **Run anyway** and **Sync students now** (or **Sync mentors now**). It has no **Cancel sync** button then, and none is needed: a new run replaces the stopped one.
- The sync stops when Airtable returns no records, or fewer than half the accounts the site has linked. It never stops when nothing is linked yet.

### Connect the site to Airtable for the first time

*When:* a new site, or a site whose connection was never set up. The Overview's **Settings** card says "Airtable is not connected yet."

1. On Airtable, create a personal access token with the scopes listed under **Token scopes**, **Details**, and give it access to the program's base.
2. Open `WPCredits Program > Settings`, tab **Connection**.
3. Paste the token into **Personal Access Token**.
4. Choose the base in **Base ID**. The list of bases appears only once a token is saved; before that, or with no list, type the base's ID.
5. Press **Save settings**. The **Tables** lists fill from the saved base.
6. Check each table under **Tables**: **Mentors table**, **Students Reports table**, **Students table**, **Institutions table** and the others. Choose any that is wrong.
7. Press **Save settings** again.

**What happens next**

- The Overview's **Settings** card says "Airtable is connected." once a token and a base are saved.
- The syncs can now run. Without a token and a base, a sync stops with "Add an Airtable Personal Access Token and Base ID before syncing."

**Good to know**

- The scope `schema.bases:read` is optional. Without it, the site cannot list the bases, tables, columns, statuses and stages, so each of those settings becomes a box you type in.
- `data.records:read` is required. `data.records:write` is needed wherever the site writes to Airtable: among others, promoting mentors in the Mentor Status Checker, a Mentors row for a mentor made from an application, and student registrations.

### Replace the Airtable token

*When:* the token is about to expire, was shared by mistake, or belonged to someone who left.

1. On Airtable, create the new token with the same scopes and access to the same base.
2. Open `WPCredits Program > Settings`, tab **Connection**.
3. Paste the new token into **Personal Access Token**.
4. Press **Save settings**.
5. Open the **Sync** tab of `WPCredits Program > Students` and press **Sync students now** to check the new token works.
6. Delete the old token on Airtable.

**What happens next**

- The site uses the new token from the next request. The old one is not kept.

**Good to know**

- The field never shows the saved token. Leaving it blank keeps the current token, so a Save of the tab does not erase it.
- **Schema token** is a second, optional token, used only by the Track Builder. Type `remove` in it to take it away.

### Use a status that was just created in Airtable

*When:* someone added a new status in Airtable and it is missing from the boxes on the **Students and mentors** tab.

1. Open `WPCredits Program > Settings`, tab **Connection**.
2. Press **Read the lists from Airtable again**. The screen says "The lists were read again."
3. Open the tab **Students and mentors**, section **Who is on the program**.
4. Tick the new status under **Currently mentoring** or **Past students**.
5. Press **Save settings**.

**What happens next**

- Students holding the status count as current (or past) from the next sync.

**Good to know**

- The statuses are read from Airtable once a day by themselves, so the button is only needed when you cannot wait.
- A box that is locked says "Kept while ... is live in the Track Builder." To untick it, first take that track off the live site in the Track Builder.

### Open student registrations

*When:* the program is ready to let students register through the form on this site, and the Airtable token has the scopes `data.records:write` and `schema.bases:read`.

1. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **Student registrations**.
2. Tick **Registrations from students** ("Take student registrations through the form on this site").
3. Under **Institutions offered**, tick the stages of the schools the form should list. *Confirmed* is ticked by default.
4. Press **Save settings**.
5. Open the address under the switch and check the form lists the right schools.

**What happens next**

- Each registration is written to the Airtable Students table with an empty Status. An address that already has an account here, or is registered already, is not written.
- Every registration is listed on `WPCredits Program > Students`, tab **Registrations**.

**Good to know**

- With no stage ticked, or no school at a ticked stage, the form says registrations are closed.
- Without those two scopes, a registration waits on the site and is not written to Airtable.
- A new partner school appears on the form after the next institutions sync.
- If the address line says "The page is missing: re-activate the plugin to recreate it.", do that before you switch on.

### Put the Student Interest Form on a page

*When:* you want students whose school is not in the program yet to tell you about it.

1. Create a page, or open an existing one, in `Pages`.
2. Add the **Student Interest Form** block. Or type the shortcode `[wpcpm_student_interest]`.
3. Publish the page.
4. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **Interest from students**.
5. Check that **Student interest form** is ticked and that the page's address now shows under it.
6. Under **Who hears of a new interest**, untick **Mail every Administrator account instead of the people below**, tick the people to tell, and press **Save settings**.

**What happens next**

- Each interest is stored on the site, on `WPCredits Program > Students`, tab **Interests**. Nothing is written to Airtable.
- The people under **Who hears of a new interest** get an email naming the school and its country, with a link to the tab.

**Good to know**

- The form must be on a page, not a post. Until a published page holds it, the screen says "The form is on no published page yet".
- The form also stays closed while the site's countries list is empty. Run **Sync institutions now** on the Institutions screen's **Sync and storage** tab.

### Open applications to mentor

*When:* the program is ready to take mentor applications on the site.

1. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **Mentor applications**.
2. Type the course link into **Mentors' course invitation link**. Only an https link is kept.
3. Under **Who is mailed**, untick **Mail every Administrator account instead of the people below**, then tick the people to tell.
4. Tick **Applications from mentors** ("Take mentor applications through the form on this site").
5. Press **Save settings**, then open the address under the switch to see the form.

**What happens next**

- Every application waits on `WPCredits Program > Mentors`, tab **Waiting for review**. Nothing is written to Airtable.
- The people under **Who is mailed** get an email with the applicant's name and WordPress.org profile.

**Good to know**

- **Select** is refused while the course link is empty, because the selection email would have no link.
- **Make mentors from applications** is a separate switch. While it is off, a selected applicant whose course shows waits under *Selected (course pending)*.

### Open the institution application form

*When:* the program is ready to take school applications on the site.

1. Open `WPCredits Program > Settings`, tab **Institutions**, section **Applications and enrollment**.
2. Tick **Applications from institutions** ("Take applications through the form on this site").
3. Check the **Areas of responsibility** below, so each country has an Administrator.
4. Press **Save settings**.
5. Open the address under the switch to see the form.

**What happens next**

- Every application is stored for an Administrator on the Institutions screen. Nothing is created until somebody approves it.
- The applicant gets an email with the application's reference.
- The country's Administrator is emailed. With no Administrator for the country, the people under **Who is emailed about institutions** are.

**Good to know**

- **Enrollment lists from institutions** is a separate switch. It lets a school send a list of students from its Institution Dashboard.
- While **Applications from institutions** is off, the page shows one sentence saying applications are closed.

### Give a country or a region to an Administrator

*When:* a new Administrator joins, or the work is shared out again.

1. Open `WPCredits Program > Settings`, tab **Institutions**, section **Areas of responsibility**.
2. For a region, choose the Administrator beside it under **Regions**.
3. For one country, use **Add a country**: type the country, then choose the **Administrator** under it.
4. To change or remove a country that is listed already, choose another name, or **Nobody**, in its own row under **Countries**.
5. Press **Save settings**.
6. Read **Who has what** to check the result.

**What happens next**

- A new school from that country is assigned to that Administrator, and only they are emailed about it.
- A country with its own Administrator goes to them, whatever its region says.
- Schools assigned before keep their Administrator. To change one, open `WPCredits Program > Institutions`, tab **Pipeline**, press the school's name, and use **Change who follows it up** on its details card.

**Good to know**

- Adding a country that already has an Administrator is refused: "... is already given to .... Change it in its own row, so the country has one Administrator."
- A refused save keeps none of the areas you changed. Fix the row and save again.
- To put a country in another region, use **Move a country** under **Countries by region**.

### Close a "no area" gap

*When:* the Pipeline shows "Countries named by institutions with no Administrator in the areas of responsibility:", or a school's country is marked *no area*.

1. Open `WPCredits Program > Institutions`, tab **Pipeline**, and note the countries in the list.
2. Open `WPCredits Program > Settings`, tab **Institutions**, section **Areas of responsibility**.
3. Give each country an Administrator under **Add a country**, or give its region one under **Regions**.
4. Press **Save settings**.
5. Open the **Pipeline** again. The line now says "Every country an institution names has an Administrator."

**What happens next**

- New schools from those countries are assigned from now on.

**Good to know**

- Closing a gap does not assign the schools already listed. Press each school's name on the **Pipeline** and assign it on its details card.
- A school marked *no country* has no country in Airtable. Set its country there; no area can cover it until then.

### Choose who is emailed about institutions, mentors and students

*When:* the wrong people get the program's emails, or nobody you expect gets them.

1. Open `WPCredits Program > Settings` and find the list:
   - **Who is emailed about institutions**: tab **Institutions**, section **Collaboration Agreements**.
   - **Who is mailed**: tab **Students and mentors**, section **Mentor applications**.
   - **Who hears of a new interest**: tab **Students and mentors**, section **Interest from students**.
2. Untick **Mail every Administrator account instead of the people below** if it is ticked. While it is ticked, the names and addresses below it are ignored.
3. Tick the Administrators who should get the emails, or type other addresses on the **Other addresses** line.
4. Press **Save settings**.
5. To go back to mailing every Administrator account, tick the box again and save.

**What happens next**

- The next email of that kind goes to the people you named.
- **Who is mailed** gets each new mentor application. **Who hears of a new interest** gets each new student interest.
- **Who is emailed about institutions** gets a new application from a school nobody is assigned to, and, for every school, a signed agreement uploaded for review, the nightly reminder of agreements waiting, the notice that the agreement template cannot be used, a school's last member leaving, and a school's request to change its details.

**Good to know**

- "Every Administrator account" means every account with the WordPress Administrator role, technical ones too. Name somebody.
- Unticking the last person is refused: "Name at least one address, or tick the box above to mail every Administrator account." A typed entry that is not an email address refuses the whole list.
- A school's new application goes only to its country's Administrator when it has one; with none, it goes to the list. A school added in Airtable whose country no area covers emails nobody.

### Try the quiet-students list in Preview

*When:* before you let the site send reminders, to see who they would reach.

1. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **Quiet students**.
2. Set **Days of quiet**, from 7 to 60. The default is 14.
3. Set **Daily list** to **Preview: Administrators see the card, nothing is mailed**.
4. Press **Save settings**.
5. After the next 06:00 UTC run, open `Administrator Dashboard > Watch` and open the card **Students gone quiet**.

**What happens next**

- The list is made once a day, at 06:00 UTC. Until the first run the card says "Not checked yet, the first check runs overnight".
- Nobody is emailed. The **Reminder** column says *Would remind* for each student who would get one.

**Good to know**

- **We've already talked** on a row takes the student off the list.
- Only active students on a track are watched. A student with a call or group session booked ahead is not on the list.
- A student of a test institution is listed with *Not mailed: test institution* and never emailed.

### Switch the quiet-students reminders on

*When:* the Preview list looks right and the program wants the reminders to go out.

1. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **Quiet students**.
2. Set **Daily list** to **On**.
3. Press **Save settings**.

**What happens next**

- From the next daily run, each quiet student gets one reminder for each quiet spell.
- Each mentor gets one email a day listing their students who were just reminded.
- On Mondays, each Administrator who has quiet students gets one list.

**Good to know**

- Students who were already quiet when you switched on are not emailed. Their row says *Not mailed: quiet before the switch went On*.

### Stop the quiet-students reminders at once

*When:* a reminder went to the wrong people, or the program wants it to stop.

1. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **Quiet students**.
2. Set **Daily list** to **Off**.
3. Press **Save settings**.

**What happens next**

- No more reminders are sent. A run that is sending stops before its next email.
- The list is deleted, and the **Students gone quiet** card is no longer shown.

**Good to know**

- **Preview** also stops the emails, but keeps the card.

### Switch on the first-week checklist

*When:* the program wants new students to see a list of their first steps on the Student Report Card.

1. If you have your own text for the *Start here* page, publish it first as a page at the address `/start-here/`.
2. Open `WPCredits Program > Settings`, tab **Students and mentors**, section **New students**.
3. Tick **First-week checklist** ("Show new students the "Your first weeks" card").
4. Press **Save settings**.

**What happens next**

- New students see the card **Your first weeks** on their Student Report Card. It gates nothing.
- The site makes a public *Start here* page, and a *Team Matcher* page when the Find Your Team plugin is active, unless pages at those addresses exist already.

**Good to know**

- "New" means students whose track began in the four weeks before the box was first ticked, and everyone who joins after. Switching off and on again does not move that date.
- A page at `/start-here/` or `/find-your-team/` that exists already is used as it is. The site never rewrites it.

### Require two-factor sign-in for a role

*When:* you want the people in a role to give a code as well as their password.

1. Open `WPCredits Program > Settings`, tab **Security**, section **Two-factor authentication**.
2. Under **Roles that must use it**, tick the roles: **Administrators**, **Student**, **Mentor**, **Institution**, **Sponsor**.
3. Press **Save settings**.
4. Read **Where it stands** to see how many accounts in each role are covered.

**What happens next**

- From their next sign-in, a person in a ticked role is asked for a code, which is emailed to them. They need to set up nothing first.
- Each person can set up an authenticator app on their own profile instead.

**Good to know**

- The setting does nothing until the Two Factor plugin is active. The screen then says "Nobody is asked for a second factor, because the Two Factor plugin is not active on this site."
- **Administrators** and **Institution** are ticked by default. Untick every box to ask nobody.

### Let an Administrator use Claude with the site

*When:* an Administrator wants to ask Claude questions that the site answers from its own records.

1. Open `WPCredits Program > Settings`, tab **Security**, section **Claude access (MCP)**.
2. Tick **Turn it on** ("Let Claude read the program's figures").
3. Under **Who may connect**, tick the Administrators who may use it.
4. Press **Save settings**.
5. Each ticked person opens their own profile in `Users` and creates an application password whose name begins with *Claude*, for example *Claude - laptop*.
6. They give Claude their username, that password and the address the screen shows: "Claude connects at ...".

**What happens next**

- Claude can read answers from the site, and change nothing.
- Every answer and every refusal is listed under **Latest calls**.

**Good to know**

- Nothing can connect while the MCP Adapter plugin is not active, or is older than version 0.7.1. The line under the switch says so, and shows the address only when the adapter is ready.
- A password with any other name does not open this address. A password named *Claude* opens nothing else.

### Take away someone's Claude access

*When:* a person leaves the team, or should no longer use Claude with the site.

1. Open `WPCredits Program > Settings`, tab **Security**, section **Claude access (MCP)**.
2. Untick the person under **Who may connect**.
3. Press **Save settings**.
4. Delete their application password named *Claude* on their profile in `Users`.

**What happens next**

- Their next call is refused. While their password still exists, **Latest calls** shows *refused: not_allowed*. Once the password is deleted, the call fails at sign-in and writes no row.

**Good to know**

- To stop everyone at once, untick **Turn it on** and save.

### Find out why a Claude call was refused

*When:* an Administrator says Claude cannot reach the site.

1. Open `WPCredits Program > Settings`, tab **Security**.
2. Under **Latest calls**, find the person's row in **Who** and read **Outcome**.

**What happens next**

- *answered* means the site answered. *failed* means the site could not build the answer.
- *refused* is followed by the reason:
  - *switch_off*: **Turn it on** is not ticked.
  - *not_allowed*: the person is not ticked under **Who may connect**.
  - *wrong_password*: the password's name does not begin with *Claude*.
  - *not_manager*: the account cannot manage the program.
  - *hour_limit*, *day_limit* or *people_limit*: the person reached one of the **Limits**.
  - *fenced*: a Claude password was used for another address. **Asked** says "Refused: another address".

**Good to know**

- A call with no signed-in account writes no row. If nothing shows, check the password itself.

### Change how long applications and files are kept

*When:* the program decides to keep decided applications, set-aside files or old invitations for a shorter or longer time.

1. Open `WPCredits Program > Settings`, tab **Advanced**.
2. Under **Applications**, set **Spam kept for (days)**, **Rejected kept for (days)** and **Approved kept for (days)**.
3. Under **Agreements**, set **Discarded files kept for (days)**.
4. Under **Invitations**, set **Settled invitations kept for (days)**.
5. Press **Save settings**.

**What happens next**

- The daily clean-up deletes what is older than the new numbers.
- The application numbers apply to institution, sponsor and mentor applications. **Spam kept for (days)** and **Rejected kept for (days)** also apply to student registrations; **Approved kept for (days)** does not.

**Good to know**

- **Approved kept for (days)** at 0 keeps approved applications forever. This is the default.
- An accepted agreement is never deleted, whatever the numbers say.
- The defaults are 30, 365 and 0 days for applications, 30 for discarded files and 30 for settled invitations.

### Set up a test institution

*When:* you want to try the institution screens or emails without touching a real school.

1. In Airtable, add a row to the Institutions table whose name starts with the word TEST in capitals, for example *TEST - Example University*.
2. Open `WPCredits Program > Institutions`, tab **Sync and storage**, and press **Sync institutions now**.
3. Open the tab **Pipeline** and check the school has the **Test** mark.

**What happens next**

- The test institution is listed wherever a real one is, with the **Test** mark.
- It is left out of every count the site draws, emails or answers.
- Some messages are never sent for it: its area Administrator's email about a new school, its students' quiet-student reminders, and the Slack announcement of a newly Confirmed school.

**Good to know**

- Only TEST in capitals, as a word on its own, counts. *Test College* and *TESTAMENT Academy* are real schools.
- Renaming it away from TEST makes it real again at the next institutions sync.

### Check that agreement files are safe on the host

*When:* after a move to a new host, or when someone asks whether the signed Collaboration Agreements can be downloaded by a stranger.

1. Open `WPCredits Program > Institutions`, tab **Sync and storage**.
2. Find the card **Storage**.
3. Press **Run probe**.
4. Read the answer on the card.

**What happens next**

- "The host refuses direct requests to the private directory" is the answer you want.
- "The host hands out files in the private directory to anyone who asks" is a warning. The files are encrypted, but tell the host to block that folder.
- "The probe could not tell what the host does" gives the host's answer or the error, with the date.

**Good to know**

- The card also says whether the site can encrypt files. If it cannot, uploads are refused until the host enables it.
- The probe runs when the plugin is activated and when you press the button. It never runs on its own.

### Run the seed command

*When:* once, to fill the areas of responsibility, the booking links, the school assignments and the tutor lists from Airtable. It needs command-line access to the server (WP-CLI).

1. Run **Sync institutions now** first, so the site has its countries list.
2. Run `wp wpcredits institutions_seed --dry-run` and read the summary. Nothing is written.
3. Run `wp wpcredits institutions_seed` and answer the question "Write this to the site?".
4. Read the lines that start with "Not assigned:" and "Not resolved:".
5. Assign each school that is left on its details card on the Institution Dashboard.

**What happens next**

- Each country whose Airtable contact has an Administrator account on the site gets that person as its Administrator, unless the site has areas already. The summary names the contacts it could not match.
- Schools are assigned to their country's Administrator, or else to the person who first handled them.
- Tutors are added from the Tutors table. No email is sent.

**Good to know**

- The command never replaces what the site holds already: an area, a booking link, an assigned Administrator, a listed tutor or a school set to **Nobody**.
- With areas set already, the summary says "The areas are already set on the site, so they were left as they are."

### Before you uninstall the plugin

*When:* the site will stop using the plugin for good. Deactivating it alone deletes no data; pressing **Delete** does.

1. Ask each person with Claude access to delete their application password named *Claude*.
2. Check that `Settings > General` holds an **Administration Email Address** that someone reads.
3. In `Plugins`, deactivate the plugin, then press **Delete**.

**What happens next**

- The plugin's settings, records and history are deleted, including applications, agreements' records, invitations, imports and semester reports.
- Accounts stay. Accounts that held only a program role become Subscribers, and the program details stored on accounts are removed.
- The signed agreement files and the key that opens them stay. An email lists them, sent to the site's administration address. With no signed files, no email is sent.

**Good to know**

- Without the plugin, an application password named *Claude* opens everything its account can. That is why step 1 comes first.
- The *Start here* and *Team Matcher* pages, and files in the Media Library, stay.

### Change the words of an email and test it

*When:* an email the site sends needs different words. You find every email under `WPCredits Program > Tools > Emails`.

1. Open `WPCredits Program > Tools > Emails`. The **Templates** tab opens, with one table per area.
2. Find the email by its name in the **Email** column. **Who receives it** and **What sends it** tell similar emails apart.
3. Press **Edit** on its row.
4. Change the **Subject** and the **Body**. Keep every placeholder that the **Placeholders** table marks **Required**.
5. Press **Save**.
6. Press **Send me a test**.
7. Read the test in your own inbox. Its subject starts with `[Test]`.

**What happens next**

- The site says "Saved. The email is sent as you wrote it from now on." The email's **State** reads **Edited**, and **Last edited** shows your name and the time.
- The test goes to your own address and to nobody else, filled with the example values from the **Placeholders** table.
- The **Preview** on the same page shows the email as it is sent now, with the example values and the signature and footer.

**Good to know**

- **Send me a test** sends what is saved, not what is in the boxes. With typing not saved yet, it sends nothing and says so.
- A sentence whose placeholders are all empty is left out of the email.
- If your words read the same as the plugin's own, the site says "Saved. It reads as the shipped text, so it is sent as shipped."

### Undo a change to an email

*When:* an edited email should go back to the plugin's own words. Its **State** on the **Templates** tab reads **Edited**, or starts "Edited, and the shipped text has changed since" or "Edit set aside".

1. Open `WPCredits Program > Tools > Emails`, **Templates** tab.
2. Press **Edit** on the email's row.
3. Open **The shipped text** under the buttons, if you want to read the plugin's words first.
4. Press **Restore the original**.
5. Confirm the question "Restore the original? Your edit is dropped, and the shipped text is sent from now on."

**What happens next**

- The site says "The original is back: the shipped text is sent from now on."
- The **State** reads **Shipped text** and **Last edited** reads **Not edited**.

**Good to know**

- **Restore the original** is there only while the email has an edit.
- Your edit is not kept anywhere after the restore. Copy its words first if you may want them again.

### Change the signature and footer of every email

*When:* the block at the end of every email the site sends needs different words.

1. Open `WPCredits Program > Tools > Emails`.
2. Open the **Signature and footer** tab.
3. Change the text in the **Signature and footer** box, under the **Settings** card.
4. Press **Save**.
5. Press **Send me a test** and read the test in your own inbox.

**What happens next**

- The site says "Saved. Every email ends with the signature and footer as you wrote it from now on."
- Every email the plugin sends ends with this block, after a line holding `-- `.

**Good to know**

- The block is not added to the emails WordPress and the Two Factor plugin send themselves.
- If the footer has a dashboard line and the Administrator Dashboard is published, the test's link is the Administrator Dashboard, because the test went to you. Everybody else's emails link to their own page, and somebody with no account gets no dashboard line.
- After an edit, **Restore the original** appears here too, as for any email.

### Set the contact address the emails name

*When:* the emails should tell people where to write with questions, or that mailbox changes.

1. Open `WPCredits Program > Tools > Emails`.
2. Open the **Signature and footer** tab.
3. In the **Settings** card, type one address in **Contact address in emails**. Use a mailbox the Administrators read.
4. Press **Save settings**.

**What happens next**

- Every email's footer names this address, as long as the footer still has its sentence for questions.
- With the box empty, the footer leaves out that sentence.

**Good to know**

- Anything but one valid address is refused, and the address saved before is kept. The screen then asks for one email address, or an empty box to name none.

### Find out what the site sent to someone

*When:* a student, a mentor or a school says an email did not arrive, or you want to know what they were sent.

1. Open `WPCredits Program > Tools > Emails`.
2. Open the **Log** tab.
3. Type their address or name in **Search address, name or subject**.
4. Narrow the list with **Area**, **Status**, **Email** or **When**, if it is long.
5. Press **Filter**.
6. Read the rows: **When**, **To**, **Email**, **Subject** and **Status**.

**What happens next**

- **Handed to the mail server** means the site passed the email on. It does not mean it reached the inbox.
- **Failed** means the site could not send it. The reason is printed under the status.
- **Not confirmed** means the site never learned whether the email went.

**Good to know**

- The log holds the emails of the last 30 days only.
- The log shows the subject, not the text. To read the text, open the email on the **Templates** tab.
- Rows marked **Test** are tests an Administrator sent to themselves.

### An email edit was refused

*When:* you pressed **Save** on an email and the message at the top starts "Nothing was saved".

1. Read the message at the top of the editor. Your typing is still in the boxes.
2. Make the change the table below gives for that message.
3. Press **Save** again.

| The message says | What to do |
| --- | --- |
| the subject is empty, the body is empty, or the signature and footer is empty | Write the missing text. |
| this email needs `{...}` | Put the named placeholder back, spelled as in the **Placeholders** table. |
| `{...}` goes in the body, never in the subject | Move that placeholder from the subject to the body. |
| `{...}` is not a placeholder of this email | Correct its spelling, or remove it. Use only the placeholders the table lists. |
| WordPress would remove part of what you typed | Put a space after each `<` (or write "less than") and after each `%`. |
| the subject is longer than 200 characters, or the body (or the signature and footer) is longer than 10,000 | Shorten it. |
| someone changed this email after you opened it | Check your words against the email as it is now, then save again. |

**What happens next**

- When the save goes through, the site says "Saved. The email is sent as you wrote it from now on."

**Good to know**

- A **State** that starts "Edit set aside" means a saved edit no longer fits the email. The plugin's own words are sent until you fix it.
- To fix it, open the email, put in or take out the placeholder the state names, and press **Save**. Or press **Restore the original**.

### Write a header notice

*When:* one audience needs a message at the top of the pages they open, such as a deadline or a planned outage.

1. Open `WPCredits Program > Tools > Header notices`.
2. Find the card of the audience: **Students**, **Mentors**, **Institutions**, **Sponsors** or **Administrators**.
3. Write the notice in that card's editor.
4. Press **Save this notice** under that editor.

**What happens next**

- The site says "The notice for Students is saved." (with the audience's name). The card reads **Showing now.**
- The notice appears at the top of the content of the site's pages, for signed-in people in that audience only.
- Saving one notice changes no other audience's notice.

**Good to know**

- Someone in two audiences sees both notices. An Administrator who also mentors gets the Mentors notice and the Administrators notice.
- A notice may hold what a WordPress post may hold, such as links, lists and images. Scripts and other markup a post cannot hold are removed when you save.
- If the site says the notice "was changed elsewhere after this page was opened", your text is still in the box. Check it against the notice as it is now, and save again.

### Switch a header notice off

*When:* a notice is out of date and should stop showing.

1. Open `WPCredits Program > Tools > Header notices`.
2. Find the card of the audience. It reads **Showing now.**
3. Delete all the text in that card's editor.
4. Press **Save this notice**.

**What happens next**

- The card reads **Not showing.** Nobody in that audience sees a notice any more.

**Good to know**

- There is no separate switch: an empty notice is off.

### Start a new track

*When:* the program needs a track that does not exist yet.

1. Open `WPCredits Program > Tools > Track Builder`.
2. Press **New track**.
3. Type the **Name**, the **Airtable status** and the **Key**.
4. Paste the **Learn course** link if the track follows a Learn course. Leave it empty if it does not.
5. Press **Create the track**. The track's own page opens.
6. Fill in the other rows the track needs, such as **Hours target**, **Key chip color** and **Partners**.
7. Press **Save the track**.

**What happens next**

- The site says "The track was created, as a draft with no questions yet." Its **State** on the list is **Draft**.
- Nothing reaches students until the track is published.
- With a course link, the course's lessons are listed beside the questions. If you left **Name** empty, the name is taken from the course.

**Good to know**

- The **Airtable status** must be one no other track has. Once the track is published, its status and its key cannot change.
- The **Key** is 2 to 20 lowercase letters, digits or hyphens.
- To start from an existing track, press **Duplicate** on its row, type a new name, status and key, and press **Make the copy**. The questions come with the copy; the Learn course, hours target, partners and partners' narrative do not.

### Add, move or remove a question

*When:* a track's form needs a new question, a question in another place, or one question less.

1. Open `WPCredits Program > Tools > Track Builder` and press **Edit** on the track's row.
2. Under **Questions**, find the group: **Total hours**, **Onboarding**, **Project** or **Wrap-up**.
3. In that group's add row, type the **Airtable column**: the name of the column in Students Reports that holds the answer.
4. Type **What the student reads** and choose the **Control**.
5. If the group shows **Under lesson**, choose the Learn lesson the question reports on, or leave **None**.
6. Press **Add a question**. The question's own page opens.
7. Fill in the rows you need, such as **Help under the box**, and press **Save the question**.

**What happens next**

- The question is saved to the draft. Nothing reaches students until the track is published.
- A question whose column is not in Airtable yet says "Publishing will create this column in Airtable."

**Good to know**

- To move a question inside its group, press the up arrow (**Move up**) or the down arrow (**Move down**) on its row. To move it to another group, change **Group** on its page.
- To remove a question, press **Remove** on its row and confirm. Its column, and whatever students wrote in it, stay in Airtable.
- One column holds one question. **Total hours** holds the Hours question alone, so it has no add row once the track has it.

### Publish a new track

*When:* a draft track is ready for students. Its **State** on the Track Builder list reads **Draft**.

1. On the Track Builder list, press **Preview** on the track's row and check the form as a student will see it.
2. Press **Publish** on the track's row. A screen headed with *Publishing* and the track's name opens.
3. If it shows **This track cannot be published yet:**, fix each line it lists, then open the screen again. A line about a column of the wrong type or with missing choices means somebody changes that column in Airtable first.
4. Read **Columns**. If it says no schema token is configured, create the listed columns in Airtable with the type and choices named, then open the screen again.
5. Do the three things the screen asks for under **What the site cannot do** in Airtable, and press **I have done this** beside each. The site does not check them, so they do not stop the publish.
6. If publishing creates columns, type the track's name in the box above the button.
7. Press **Publish this track**.

**What happens next**

- The site says "The track is live", and how many columns it created in Airtable. The list's **State** reads **Published**.
- Publishing adds the track's status to **Currently mentoring** in Settings.
- Students on that status in Airtable get this form on their Student Report Card.

**Good to know**

- While a line is listed under **This track cannot be published yet:**, or columns are missing and no schema token is set, the screen has no **Publish this track** button. Lines under **Worth knowing before you publish:** do not stop the publish, but read them.
- The three Airtable steps are **Add the status to the reports automation**, **Create the welcome email automation** and **Add the two Status choices**, with the exact values on the screen. Without the first, no student on the track gets a report row and their Student Report Card stays empty.
- Columns publishing creates in Airtable can never be removed by the site. That is why it asks for the track's name, and a wrong name publishes nothing.

### Change a track that is live

*When:* a published track needs other words, another question or another course. Students keep the published form until you publish the change.

1. Open `WPCredits Program > Tools > Track Builder` and press **Edit** on the track's row.
2. Make the change, then press **Save the track** or **Save the question**.
3. Press **Preview** to see the changed form as a student will.
4. Back on the list, check that the **State** reads **Unpublished changes**, and press **Publishing** on the row.
5. Fix anything listed under **This track cannot be published yet:**. Lines under **Worth knowing before you publish:** do not stop the publish.
6. Press **Publish the changes**. If the screen creates columns, type the track's name first.

**What happens next**

- Students on the track see the changed form on their Student Report Card. The **State** reads **Published** again.

**Good to know**

- A published track keeps its **Airtable status** and its **Key**.
- A published question keeps its column, its control and its choices; its words can change. To ask it differently, remove it and add a new question with a column of its own.
- **History** on the track's row shows the published copy against the draft, and who saved what.

### Take a track off the live site

*When:* a track should stop running.

1. Open `WPCredits Program > Tools > Track Builder` and read the track's **Students** column.
2. If it is above 0, move those students to another status in Airtable, and wait until the column reads 0.
3. Press **Publishing** on the track's row.
4. Press **Take it off the live site**.

**What happens next**

- The site says "The track is a draft again. Nothing was changed in Airtable, and its status is still in "Currently mentoring"."
- The track can be published again later. It cannot be deleted, because it was published once.

**Good to know**

- While students hold the track's status, the site refuses and says how many are on it.
- The four original tracks have no **Take it off the live site** button. The screen says "The program's original tracks always run: edit the track and publish the change instead."
- The **Students** column counts the students the site last synced from Airtable, so it changes after the next students sync.

### Show a certificate code on the Student Report Card

*When:* students need to read a value the program writes for them in Airtable, such as a certificate code, without being able to change it.

1. Open `WPCredits Program > Tools > Track Builder` and press **Edit** on the track's row.
2. Add a question in the group where students should see the code. Use the **Airtable column** that holds the code and a control such as **Text, one line**.
3. On the question's page, tick **Filled in by the program: students see it but cannot change it**.
4. Write a **Help under the box** line that tells students what to do with the code.
5. Press **Save the question**.
6. Publish the track, as in the job *Change a track that is live*.

**What happens next**

- The Student Report Card shows the question's words, then the code as text, then the help line. Before the code is in Airtable, it shows the words and the help only.
- Nothing on the card can change or empty the code, also when an Administrator fills in the report for a student.

**Good to know**

- The site never writes the code. A wrong code is corrected in Airtable.
- The box can be ticked on a question that is already published, because it changes no column. Students see it once the track is published again.
- It is not offered on a **Screenshot**, a **Contribution team** or the Hours question.

### Make a post readable by one group

*When:* a post or a page is meant for students, mentors, schools or sponsors only.

1. Open the post or the page in the WordPress editor.
2. Find the **Program access** box in the sidebar.
3. Choose who can read it under **Who can read this?**.
4. Save or update the post.

**What happens next**

- Somebody signed out who opens it is sent to log in first.
- Somebody signed in without that level reads "This content is limited to:" and the level's name, instead of the post.
- Administrators can read every level, and the post's author can always read it.

**Good to know**

- The levels are **Public**, **Student level**, **Mentor level**, **Institution level**, **Sponsor level**, **Students and mentors** and **Administrators only**.
- The levels do not nest: a mentor cannot read a **Student level** post. For both groups, choose **Students and mentors**.
- Only posts and pages have the **Program access** box.

### Publish a program update

*When:* there is news for one audience, or for everybody, to read on their dashboard.

1. Write a new post in the WordPress editor.
2. Put it in the **Updates** category.
3. In the **Program access** box, choose who can read it under **Who can read this?**.
4. Publish the post.

**What happens next**

- It is listed under **Program updates and announcements** on the Student Report Card, the Mentor Report Card and the Institution Dashboard of the people who can read it. Each lists the five newest, with **All updates** when there are more.
- Each of these lists **Public** posts and posts at its own level. Students and mentors also get **Students and mentors** posts.
- On the Administrator Dashboard, **Program updates** in the side menu lists **Public** and **Administrators only** posts. On the Sponsor Dashboard, it lists **Public** and **Sponsor level** posts. The item appears only while there is a post to list.

**Good to know**

- An update for Administrators alone can be a private post at **Administrators only**. A private post is listed on the Administrator Dashboard and nowhere else.
- The site finds the category by its name "Updates" and never creates it. Without that category, the lists stay empty.

### Set up Need help?

*When:* mentors and Administrators should be able to ask questions answered from the WordPress documentation, or answers stopped coming.

1. Open `WPCredits Program > Tools > Need help?`.
2. In the **Settings** card, check that **Switch it on** beside **Need help?** is ticked. It starts ticked.
3. Choose **Google AI Studio (Gemini)** in **Answer provider**.
4. Paste the provider's key in **Provider API key**.
5. Leave **Model** as it is.
6. Choose who may ask under **Who can ask**, and set **Questions per person per hour**.
7. Press **Save settings**.
8. Type a question under **Try a question** and press **Ask**.

**What happens next**

- The answer under **Try a question** is exactly what somebody on the program would see.
- People ask from [[Need help?]] in the site header and from the Resources section of the Mentor Report Card.
- Each question, and the pages found for it, go to the provider's company. Nothing is stored on this site.

**Good to know**

- With **Answer provider** left at **None - questions cannot be answered**, nobody gets an answer.
- **Who can ask** starts at **Mentors and administrators**. Nobody signed out can ask, whatever it says.
- **Questions per person per hour** starts at 20, and 0 removes that limit. The whole site still gets at most 300 questions answered a day.
