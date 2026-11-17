## Who can read what

Every post and page has a **Program access** box in the editor sidebar. Its list, **Who can read this?**, sets the level. A post with no level is **Public**.

{{image:admin-access-level|The access control sits with the post's own settings. Administrators can always read every level.}}

| Level | Who can read it |
| --- | --- |
| **Public** | Everyone, logged in or not. |
| **Student level** | Students and Administrators. |
| **Mentor level** | Mentors and Administrators. |
| **Institution level** | A school's representatives and tutors, and Administrators. |
| **Sponsor level** | A sponsor's accounts and Administrators. |
| **Students and mentors** | Students, mentors and Administrators. |
| **Administrators only** | Administrators. |

Rules:
- The levels do not nest. A mentor cannot read a Student-level page.
- An account with two roles reads both levels. A post's author always reads it.
- A post a sponsor's account writes is set to **Students and mentors**. An Administrator can change it.
- A restricted post is left out of listings, feeds, search and embeds. Where it is still reached, its text is replaced by a notice.
- Opened by address, it sends a logged-out visitor to the login form. A logged-in reader without the level is told so.

Step by step: [[Make a post readable by one group]].

### Program updates and announcements

The program's news is ordinary posts in a category named *Updates*, or one under it. You create the category: the plugin never does. Each post's level decides where it shows.

| Where | Levels listed |
| --- | --- |
| Student Report Card, column **Program updates and announcements** | **Public**, **Student level**, **Students and mentors** |
| Mentor Report Card, the same column at the top | **Public**, **Mentor level**, **Students and mentors** |
| Institution Dashboard, **Program updates** in the menu | **Public**, **Institution level** |
| Administrator Dashboard, **Program updates** in the menu | **Public**, **Administrators only**, private posts included |
| Sponsor Dashboard, **Program updates** in the menu | **Public**, **Sponsor level** |

Rules:
- A page lists only its own levels, even for an Administrator.
- The column shows the newest five, with **All updates** when there are more.
- A private post shows on the Administrator Dashboard alone, such as a biweekly site update set to Private.
- While no *Updates* category exists, the two columns read *Nothing new right now.* and the three menus have no **Program updates** item.
- **Program updates** in a menu opens the newest update. It is there only while an update exists.
- The update opens in the dashboard's frame, with up to twenty listed beside it, only where the site's theme supports that page. Otherwise it opens as an ordinary post. The list needs two or more updates.
- An Administrator viewing a sponsor opens a **Sponsor level** update in that sponsor's frame. Its menu and list keep that sponsor.
- Since 1.123.96 the Institution Dashboard has no column: **Program updates** is in its menu. An Administrator viewing a school opens an **Institution level** update in that school's frame, and its menu keeps that school.

Step by step: [[Publish a program update]].

### Viewing a page as its owner

On the Student Report Card, the Mentor Report Card, the Institution Dashboard and the Sponsor Dashboard, an Administrator sees a switcher above the page. It shows only when there are two or more names.

- **Viewing as student**, **Viewing as mentor**, **Viewing as institution**, **Viewing as sponsor**: one field, showing whose page you are on.
- **Show**: opens the page of the name you picked. Picking alone opens nothing.

Rules:
- The list runs A to Z, ignoring capitals and accents.
- Type to narrow it to names that hold what you typed, anywhere in the name.
- Press Enter or click a name to pick it. Escape puts back the name picked last.

Since 1.123.96, on the Institution Dashboard, **Viewing as institution** is one small field at the end of the page's first line, on every view:
- Picking a school opens its page at once, on the view you are on. There is no **Show** unless JavaScript is off.
- Every link in the menu keeps the school.
- With no school in the address, an Administrator who belongs to no school is asked for one first, while two or more schools exist. The page's head then reads **Choose an institution**, and the menu lists the views without counts. With one school only, or for an Administrator who is also a school's member, the page opens on that school.
- The page does not remember the school. An address that names none asks again.

Step by step: [[Open someone's page as they see it]].

### The details card on an institution's page

The card **Your institution's details** is the first card of the Institution Dashboard's **Your institution** view. A school's representatives see it once the agreement is settled, and its tutors never; Administrators see it on any school's page. Before that, the school's representatives have **Overview** and **Agreement** alone, and its tutors see no page.

| Part | What it holds |
| --- | --- |
| **Contact and agreement** | **Contact person**, **Their role**, **Signs the agreement**, **Their title**, **Students study in**, **Page about WordPress Credits** |
| **Name and address** | **Name**, **Website**, **City**, **Country**, **Contact's email** |
| **Tracks** | **Tracks offered** |
| **Assigned Administrator** | Who follows the school up, or *Nobody yet*. Administrators only. |

A detail the program lacks reads *Not given yet*.

Step by step: [[Correct a school's details yourself, and set the tracks it offers]]; [[A school asks to correct its details]]; [[Assign a school to another Administrator, or to nobody]].

#### The two forms on the details card

Each list has a closed form under it, asking the application's own questions.

| Form | Who | What a press does |
| --- | --- | --- |
| **Correct these**, under **Contact and agreement** | Representatives and Administrators | **Save the details** saves at once. |
| **Ask to correct these**, under **Name and address** | Representatives | **Send to an Administrator** raises a request and saves nothing. |
| **Correct these**, under **Name and address** | Administrators | **Save the details** saves at once. Nobody approves it. |

A save at once:
- Writes a changed contact person, or a changed detail of the second list, to Airtable. The other answers stay on the site.
- If Airtable refuses, nothing is saved on the site either. The card never names Airtable to the school.
- Adds a line to the school's history naming the details changed, never their values.
- Emails the people **Who is emailed about institutions** names, with what changed.

A request:
- Waits on the Administrator Dashboard's **Requests from institutions** card, and emails the same people a link.
- Its approval or decline is emailed to the person who asked. A withdrawal is emailed to the setting's people.

With nobody named in **Who is emailed about institutions**, every Administrator is emailed.

#### A request that waits

A school has one request at a time.

- Each row it would change reads "Waiting for an Administrator:", with the value and the day.
- Representatives see **Withdraw it** there. Administrators do not.
- **Name and address** offers no form while it waits, to anyone. Administrators are pointed to **Requests from institutions**.
- After an approval the school reads "Updated on" and the day. After a decline it reads that no change was made, with the Administrator's note if any.

#### Saves that are refused or busy

- A form with nothing changed saves nothing and sends no email.
- A refused form comes back with what was typed, and the problem under its field.
- A text the site would strip words from is refused whole, as on the application.
- The same correction sent twice, from two tabs or two accounts, is saved once.
- A save made while another is saving does nothing, keeps the typing and asks to try again in a moment.

#### Tracks and the assigned Administrator

- **Tracks offered**: the tracks the school offers that the site runs, or *None listed yet.* Only Administrators change it, under **Change the tracks**, then **Save tracks**.
- Representatives read the list and the program's contact address to write to. That is the Emails tool's **Contact address in emails**, or a built-in address while it is empty.
- A save adds a line to the school's history. The list is kept on the site, never in Airtable.
- A track taken off the live site drops out, and does not count toward the two below. It comes back, still ticked, when published again.
- **Assigned Administrator**: change it under **Change who follows it up**.

With two or more tracks ticked, the form at `/student-registration/` requires "Which track would you like to join?", with the tracks and **Not sure yet**:
- The answer goes to the Students table's **Requested track** column in Airtable. It shows under **Answers and consent** on the **Registrations** tab.
- If that column is missing, the row is written without it. The registration then reads "Requested track: the Students table has no such column."
- An Administrator still assigns the track. The row's Status is left empty.

Step by step: [[Correct a school's details yourself, and set the tracks it offers]]; [[Assign a school to another Administrator, or to nobody]].

### A student's card on an institution's page

A school's representatives and tutors open a student's card from the roster on **Students**. An Administrator opens it from the school's roster, reached through **Viewing as institution**. The card opens in place of the roster, and **Students** in the menu leads back to it.

- The student's name and program. Representatives and Administrators also read "No sign-in on record" while the student has not signed in, with a way to send the invitation again.
- **Internship duration**, **Cohort**, **Field of study**, **Tutor**, **WordPress.org**, **Contribution teams**, **Personal website**.
- **Mentor**: the name, or *No mentor yet*. A mentor is optional.
- **Mentor email**: only when there is a mentor. *Not set* when the address is unknown.
- **Student Report Card**: read only, loaded when you open it.
- **Correct this student's details**: the correction form, closed.
- **Download this student's details and course grades as a CSV file**: for representatives and Administrators.

The correction form:
- It corrects **Name on the Student Report Card**, **Full name**, **Start date**, **End date** and **Field of study**. Press **Save changes**.
- The end date must be after the start date and at most 365 days later. The field of study is one of the program's choices.
- Only the school's representatives and Administrators see it. A student with no progress record has no form and no Student Report Card.
- A save writes to the program records at once and adds a line to the school's history.
- A refused value is named, with whether the rest was saved. The section then opens by itself.

The card asks for no mentor. A mentor request already on record stays on **Requests from institutions**.

Step by step: [[Open a school's Institution Dashboard]]; [[A school's other requests (a student to add, a mentor wanted)]].

### The For Administrators section of an institution's page

**For Administrators** is the last view in the menu of every school's Institution Dashboard. Nobody who acts for the institution sees it. It holds two cards.

- **From the application**: the application's answers that Airtable has no column for, such as who signs the agreement. They stay after the application is removed. An institution approved before the site kept these answers has none: **Nothing kept from the application.**
- **History**: the latest 100 things that happened to the institution, newest first.

A History row gives when, who and what. Member rows and closed requests read in today's words; other rows show the sentence they were recorded with. A row recorded under an older name for Administrators reads in today's words too. Who is the account's name now. It reads **The site** when the site did it, and **A removed account** when the account is gone.

- Rows cover members, invitations with the address each went to, the application, the Collaboration Agreement, details, tracks, the assigned Administrator, tutors, requests, imports, students and roster refusals.
- A member row says how the account joined, in the words of **Representatives and tutors**, such as **Joined by invitation**, or why it left, such as **They removed their own access**.
- A closed request says how it was closed, with the Administrator's note when it can be shown.

Step by step: [[Open a school's Institution Dashboard]].

### Arranging the Student Report Card

Under the profile and mentor columns, the Student Report Card is a stack of up to six modules. The names below are the ones the arrows use. A module with nothing to show is left out.

| Module | Shown when |
|---|---|
| **Your first weeks** | The first-week checklist is on, for a new student |
| **Program updates and resources** | Always |
| **My course** | The student's program details are on the site; with no course, **My hours**, or **Partners of your track** |
| **Report form and feedback forms** | The student's program details are on the site |
| **My mentor call** | Always |
| **Tools from our sponsors** | On their own card: **Show the section on the Student Report Card** is on (tab **Sponsors**), and they have offers or claimed tools |

- Two small arrows at a module's top right move it at once. Only the student and Administrators see them.
- The order is the student's own.
- Through the switcher you see their order and can arrange it for them. There **Tools from our sponsors** is one line, such as *2 tools claimed*, whatever the setting says, and nothing when they claimed none.

Step by step: [[Open a student's Student Report Card, and what you can change there]]; [[Switch on the first-week checklist]].

### A student's or a mentor's own language

A student or a mentor can pick the language of their own Report Card. The switcher sits at the top right, above the title: a flag and the language's name.

- Picking a language keeps it on their account. The page and the site's emails to them follow it.
- Administrators never see the switcher. There is no setting.
- The list holds the site's language first, then each language the plugin has a translation for. With no translation, there is no switcher.
- Text only Administrators, institutions or sponsors read stays in English.
- WordPress's own words and dates follow only when that language is installed on the site.

### The views of an institution's page

Since 1.123.96 the Institution Dashboard has nothing to arrange. A menu down the side opens one view per part: **Overview**, **Students**, **Enroll students** (while **Enrollment lists from institutions** is on), **Semester reports**, **Your institution**, **Agreement**, **Events** and, for you alone, **For Administrators**.

- **Events** is for the school's own representatives, while an event is listed. Tutors and Administrators do not have it.
- A tutor has no **Enroll students**. A school whose agreement is not settled has **Overview** and **Agreement** alone. Its tutors see no page.
- The menu also holds **Program updates** while there is an update for institutions, and **Institution guide** under **Help**.
- A red tag beside a view counts what waits there for the reader, such as *1 waits*. Beside **Overview**, the tag counts all of it.
- Through **Viewing as institution**, the school stays as you change views, and the view stays as you change schools.
- The order a school saved with the old arrows stays stored and unused.
- On the **Overview** you see *Stage:* in the head, and no news under **Good to know**: news is the school's own.

The Sponsor Dashboard has nothing to arrange. A menu down the side opens one view per part: **Overview**, **Your sponsorship** (while **Sponsor tiers** is on), **Offers**, **Students reached**, **Mentors to sponsor**, **Your company** and **Agreement**. It also holds **Program updates** while there is an update for sponsors, and **Sponsor guide** while its page is published. Through the switcher, the sponsor stays as you change views, and the view stays as you change sponsors.

Step by step: [[Open a school's Institution Dashboard]]; [[Open a sponsor's Sponsor Dashboard]].

### Group sessions, from a student's card or a mentor's

On a student's card, their group sessions are in **My mentor call**. One they are on that has not started offers **Take them off the session**.

- It asks first, then works as the student's own **Leave the session**: the place goes back.
- The student gets the email **Left a group session, to the student**, with a calendar file. Its subject reads "You left the group session with" and the mentor's name.

On a mentor's card you can use **Change this session** and **Cancel the session**, as the mentor can.

- A move to another time emails the students and the mentor, with an invitation that moves it in their calendar.
- A cancel emails everybody on it and the mentor: **Group session canceled**.

Step by step: [[Take a student off a group session]]; [[Open a mentor's Mentor Report Card]].
