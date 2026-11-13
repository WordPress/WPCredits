## Tools you run on their own

Each tool has a card on `WPCredits Program > Tools` and a screen of its own under that menu. Four tools keep settings of their own: the **Mentor Status Checker**, the **Student Duplicate Finder**, **Need help?** and **Emails**.

- Each of the four has a **Settings** section on its own screen, with its own **Save settings**. It saves that tool's settings and nothing else.
- The Emails tool's **Settings** section is on its **Signature and footer** tab.
- After a save, the notice on the same screen names the tool.
- `WPCredits Program > Settings`, tab **Connection**, names the four tools, each a link to its **Settings** section.

Each card on the Tools screen can carry a status line. A tool that cannot run says why, as a warning:

- **Student Duplicate Finder**: *Airtable is not connected yet, so this tool cannot run.*
- **Need help?**: *Switched off.*, or *No AI provider is configured, so questions cannot be answered.*
- The **Mentor Status Checker** runs without Airtable. Its line then says it checks the applications selected on this site, and not Airtable's mentors.

Step by step: [[Set up Need help?]]; [[The Mentor Status Checker: check, then promote]]; [[A student has duplicate rows]].

### Header notices

`WPCredits Program > Tools > Header notices` holds one notice per audience: **Students**, **Mentors**, **Institutions**, **Sponsors** and **Administrators**. Each notice has its own editor and its own **Save this notice** button.

{{image:admin-header-notices|One editor per audience. An empty notice is not shown; there is no separate switch.}}

- An empty notice is off. There is no separate switch.
- Under each audience's name, the screen says **Showing now.** or **Not showing.**
- Saving one notice never changes another. If someone changed that notice after you opened the page, nothing is saved and your text stays in the box.
- A notice may hold what a WordPress post may hold: links, images, lists, headings. Scripts and other markup a post cannot hold are removed when you save.

Who sees a notice:

- Only people who are signed in, at the top of the content of the page they open. In the program's theme, that is the top of the dashboard card, not above the site header.
- Anyone in two audiences sees both notices, in the order above. An Administrator who also mentors sees the mentor notice first.
- An account matched to an Airtable mentor record counts as a mentor, even without the mentor role.
- **Institutions** means people who are members of an institution now. **Administrators** means every account that can manage the program.

Step by step: [[Write a header notice]]; [[Switch a header notice off]].

### Emails

`WPCredits Program > Tools > Emails` lists the site's emails you can edit, by area. An email you have not edited is sent as the plugin ships it. Every email in these tables ends with the signature and footer.

| Tab | What it holds |
|---|---|
| **Templates** | Every email you can edit, one table per area. The tool opens here. |
| **Signature and footer** | The block every email ends with, and the tool's one setting, **Contact address in emails**. |
| **Log** | Every email the site sent in the last 30 days. |

- **Contact address in emails**: the address every email names for questions, in its signature and footer. Type one email address. Left empty, the footer leaves out the sentence that names it.
- The signature and footer is not added to the emails WordPress and the Two Factor plugin send themselves.

Step by step: [[Change the signature and footer of every email]]; [[Set the contact address the emails name]]; [[Find out what the site sent to someone]].

#### Templates

The **Templates** tab has a table for each of ten areas. Its columns are **Email**, **Who receives it**, **What sends it**, **State** and **Last edited**, with an **Edit** link. **Last edited** says who saved the edit, and when.

Where an email goes to Administrators, a setting decides which addresses get it. With no address set, every Administrator account gets it.

| Emails about | The setting |
|---|---|
| Institutions: applications, agreements, details, last member | **Who is emailed about institutions** (`Settings`, tab **Institutions**) |
| Semester reports drafted | **Who reviews reports** (`Settings`, tab **Institutions**) |
| A sponsor | The sponsor's assigned Administrator; with none, **Who is mailed** (tab **Sponsors**, section **Interest mail**) |
| A new sponsor application | **Who is mailed** (tab **Sponsors**, section **Interest mail**) |
| A new mentor application | **Who is mailed** (tab **Students and mentors**, section **Mentor applications**) |
| A new interest from a student | **Who hears of a new interest** (tab **Students and mentors**, section **Interest from students**) |

The area **Invitations** holds the first email of each new account, and the email to a mentor about students assigned to them.

| Email | Who gets it | When |
|---|---|---|
| **Invitation: student** | The student | Their invitation is sent from the Students screen, or by the sync with invitation emails on. A member can resend it from the Institution Dashboard to a student who never signed in. |
| **Invitation: mentor** | The mentor | An application to mentor is activated, or the invitation is sent from the Mentors screen or by the sync |
| **Invitation: institution** | The institution's member | The invitation is sent before the Collaboration Agreement is on file |
| **Invitation: institution, agreement on file** | The institution's member | The invitation is sent once the agreement is on file |
| **Invitation: sponsor** | The sponsor's member | The invitation is sent from the Sponsors screen |
| **Student assigned, to the mentor** | The mentor | The mentors sync finds a current student assigned to the mentor that they have not been emailed about, while **Students assigned to a mentor** is on |

- An account that fits two rows gets the first of mentor, institution, sponsor, student.
- The student's invitation names the Start here page while that page is published.
- The mentor's invitation carries the welcome a new mentor gets. It ships with a line in brackets where the course invitation links and the mentor resource folder's link go. Type those links in before mentors are sent it.
- A reply to the mentor's invitation goes to **Contact address in emails**, while it holds an address.
- **Student assigned, to the mentor**: see [[Emailing a mentor about a student assigned to them]].

The area **Mentor calls** covers one-to-one calls, group sessions and series of sessions. A mentor with no meeting link set gets a second version of each confirmation, which says so.

| Email | Who gets it | When |
|---|---|---|
| **Call booked, to the mentor** | The mentor | A student books a call, and the mentor has a meeting link |
| **Call booked, to a mentor with no meeting link** | The mentor | A student books a call, and the mentor has no meeting link yet |
| **Call booked, to the student** | The student | The student books a call |
| **Group session joined, to the mentor** | The mentor | A student joins a group session, and the mentor has a meeting link |
| **Group session joined, to a mentor with no meeting link** | The mentor | As above, with no meeting link yet |
| **Group session joined, to the student** | The student | The student joins a group session |
| **Session series joined, to the mentor** | The mentor | A student presses **Join all** on a series, and the mentor has a meeting link |
| **Session series joined, to a mentor with no meeting link** | The mentor | As above, with no meeting link yet |
| **Session series joined, to the student** | The student | The student presses **Join all** on a series |
| **Group session moved, to the student** | Each student on it | A group session moves to another time |
| **Group session moved, to the mentor** | The mentor | An Administrator moves the mentor's group session |
| **Left a group session, to the student** | The student | The student leaves a group session |
| **Place on a group session released, to the student** | The student | The sync takes the student off a former mentor's session, after a new mentor |
| **Call canceled, to the mentor** | The mentor | The student or an Administrator cancels a call, or the student moves to another mentor |
| **Call canceled, to the student** | The student | The mentor or an Administrator cancels a call, or the student moves to another mentor |
| **Group session canceled** | The mentor and every student on it, but the person who canceled | A group session is canceled |
| **Call reminder, to the mentor** | The mentor | A day before a call, unless it was booked within that day |
| **Call reminder, to the student** | The student | A day before a call, unless it was booked within that day |
| **Group session reminder** | The mentor and every student on it | A day before a group session, unless it was made within that day |

The area **Institutions** covers an institution from its application to its last member.

| Email | Who gets it | When |
|---|---|---|
| **Institution application received, to the applicant** | The applicant | An institution applies, and there is a booking link to give: its assigned Administrator's, or with nobody assigned, the country contact's as it stood that day |
| **Institution application received, to an applicant with no call to book** | The applicant | An institution applies, with no such link |
| **New institution application, to Administrators** | Administrators | An application passes every check and no Administrator is assigned to it |
| **Question about an institution application** | The applicant | An Administrator asks the applicant a question |
| **Institution application declined** | The applicant | An Administrator rejects the application. The reason typed is never sent. |
| **Invitation to join an institution** | The colleague invited | A member, or an Administrator for the institution, invites a colleague |
| **Invitation to join an institution, with no sender named** | The colleague invited | As above, when the inviter's account is gone or holds no name |
| **Invitation to an institution as a tutor** | The colleague invited | A colleague is invited as a tutor, who sees the students and changes nothing |
| **Invitation to an institution as a tutor, with no sender named** | The colleague invited | As above, when the inviter's account is gone or holds no name |
| **Collaboration Agreement received, to the institution** | Every member | The institution uploads its signed agreement |
| **Collaboration Agreement uploaded by an institution, to Administrators** | Administrators | The institution uploads its signed agreement, and the courtesy scan notices nothing |
| **Collaboration Agreement uploaded by an institution with the scan's notes, to Administrators** | Administrators | As above, when the scan notices something |
| **Collaboration Agreement accepted, to the institution** | Every member | An Administrator accepts the agreement |
| **Collaboration Agreement back in force, to the institution** | Every member | An Administrator puts a revoked agreement back in force |
| **Collaboration Agreement returned with a note, to the institution** | Every member | An Administrator sends the agreement back with a note |
| **Collaboration Agreement taken out of force, to the institution** | Every member | An Administrator revokes the agreement. An Administrator with an address is assigned, and the country is known. |
| **Collaboration Agreement taken out of force, to an institution nobody is assigned to** | Every member | An Administrator revokes the agreement, in every other case |
| **Collaboration Agreements from institutions waiting for review, to Administrators** | Administrators | Once a day, while agreements have waited past the review window |
| **Collaboration Agreement for an institution could not be generated, to Administrators** | Administrators | An institution asks for the agreement, and the template cannot be used |
| **Institution has no members left, to Administrators** | Administrators | The last member leaves or is removed |
| **Institution details corrected, to Administrators** | Administrators | A member saves the contact and agreement details, or an Administrator saves any detail |
| **Institution asks to correct its details, to Administrators** | Administrators | The institution asks to correct its name, website, city, country or contact's email |
| **Correction of institution details approved, to the institution** | The person who asked | An Administrator approves the request |
| **Correction of institution details declined, to the institution** | The person who asked | An Administrator declines the request |
| **Institution withdrew its request to correct its details, to Administrators** | Administrators | The institution withdraws its request |
| **Institution assigned, to the Administrator** | The Administrator assigned | An institution is assigned to them, unless they made the assignment. A new application given to them tells them alone. |

The area **Semester reports** covers the report each institution gets.

| Email | Who gets it | When |
|---|---|---|
| **Semester report drafted, to Administrators** | Administrators | The daily job drafts a report |
| **Semester report approved, to the institution** | Each of the institution's accounts | An Administrator approves the report |
| **Consent for the semester report, to the student** | Each student who wrote an answer and has not given permission either way | An Administrator asks whether the report may mention them. Nobody asked in the last 30 days. |

The area **Sponsors** covers a sponsor from its application to the end of its term.

| Email | Who gets it | When |
|---|---|---|
| **Sponsor application received, to the applicant** | The applicant | A company applies to sponsor |
| **Sponsor application received, to an applicant whose logo was not kept** | The applicant | A company applies while the form takes more applications than usual, so the logo files are not kept |
| **New sponsor application, to Administrators** | Administrators | An application passes every check |
| **Question about a sponsor application** | The applicant | An Administrator asks the company a question |
| **Sponsor application declined** | The applicant | An Administrator rejects the application. The reason typed is never sent. |
| **Collaboration Agreement received, to Administrators** | Administrators | The sponsor uploads its signed agreement |
| **Collaboration Agreement accepted, to the sponsor** | The sponsor's accounts | An Administrator accepts the agreement |
| **Collaboration Agreement returned with a note, to the sponsor** | The sponsor's accounts | An Administrator sends the agreement back with a note |
| **Collaboration Agreement taken out of force, to the sponsor** | The sponsor's accounts | An Administrator takes the agreement out of force |
| **Collaboration Agreement back in force, to the sponsor** | The sponsor's accounts | An Administrator puts the agreement back in force |
| **Sponsor interest** | Administrators | The sponsor presses **Talk to your program contact** on the Sponsor Dashboard |
| **Request to sponsor a mentor** | Administrators | The sponsor asks to sponsor a mentor who is looking for one |
| **Codes running low** | The sponsor's accounts, and Administrators | A claim leaves an offer below its warning. Once, until codes are added. |
| **Problem with a claimed code** | Administrators | Someone reports that a claimed code did not work |
| **Sponsor post returned with a note** | The post's author | A sponsor's post is sent back as a draft with a note |
| **Sponsor offer returned with a note** | The member who sent it for review | An Administrator sends back an offer, or a change to a live one, with a note |
| **Sponsor term ending, to Administrators** | Administrators | Thirty days before the term ends, or the first daily run after. Once a term. |
| **Sponsor term ended, to Administrators** | Administrators | The daily run finds the term over before the thirty-day email went. Once a term. |

The area **Sponsor events** covers a sponsor's events and the program's own. An email whose name starts with "Program event" is about an event of the program's own.

| Email | Who gets it | When |
|---|---|---|
| **Event joined, to the person who joined** | The person who joined | Someone joins a sponsor's event, with a calendar file |
| **Program event joined, to the person who joined** | The person who joined | Someone joins a program event, with a calendar file |
| **Event left, to the person who left** | The person who left | Someone leaves a sponsor's event, with a calendar file that removes it |
| **Program event left, to the person who left** | The person who left | Someone leaves a program event, with a calendar file that removes it |
| **Event changed, to the people on it** | The people on it | An approved sponsor's event changes its time, length, title or call link |
| **Program event changed, to the people on it** | The people on it | A program event changes its time, length, title or call link |
| **Event canceled, to the people on it** | The people on it | A sponsor's event is canceled |
| **Program event canceled, to the people on it** | The people on it | A program event is canceled |
| **Event reminder, to the people on it** | The people on it | The day before an approved sponsor's event |
| **Program event reminder, to the people on it** | The people on it | The day before a program event |
| **Event reminder, to the sponsor** | The sponsor's accounts not on the event | The day before its approved event. It says how many joined, never who. |
| **Event approved, to the sponsor** | The sponsor's accounts | An Administrator approves the event, with a calendar file |
| **Event returned with a note, to the sponsor** | The sponsor's accounts | An Administrator returns the event with a note, which cancels it |
| **Event returned with a note, to a sponsor whose tier no longer includes events** | The sponsor's accounts | As above, when the tier no longer includes events or the sponsor is no longer approved |
| **Event canceled with a note, to the sponsor** | The sponsor's accounts | An Administrator cancels the event with a note |
| **Approved event changed, to the sponsor** | The sponsor's accounts | The sponsor changes its approved event |
| **Approved event changed by an Administrator, to the sponsor** | The sponsor's accounts | An Administrator, for the sponsor |
| **Approved event canceled, to the sponsor** | The sponsor's accounts | The sponsor cancels its approved event |
| **Approved event canceled by an Administrator, to the sponsor** | The sponsor's accounts | An Administrator, for the sponsor |
| **Event planned by a sponsor, to Administrators** | Administrators | The sponsor plans an event |
| **Event planned for a sponsor by an Administrator, to Administrators** | Administrators | An Administrator, for the sponsor |
| **Waiting event changed by its sponsor, to Administrators** | Administrators | The sponsor changes an event that waits for approval |
| **Waiting event changed for a sponsor by an Administrator, to Administrators** | Administrators | An Administrator, for the sponsor |
| **Approved event changed by its sponsor, to Administrators** | Administrators | The sponsor changes its approved event |
| **Approved event changed for a sponsor by an Administrator, to Administrators** | Administrators | An Administrator, for the sponsor |
| **Event canceled by its sponsor, to Administrators** | Administrators | The sponsor cancels its event |
| **Event canceled for a sponsor by an Administrator, to Administrators** | Administrators | An Administrator, for the sponsor |

"For the sponsor" means on its Sponsor Dashboard, opened by an Administrator.

The area **Mentor applications** covers people who apply to mentor through the form on the site. The three emails after a decision go only to an applicant who confirmed the address with the link in the first email. **Select** is refused while **Mentors' course invitation link** is empty (`Settings`, tab **Students and mentors**).

| Email | Who gets it | When |
|---|---|---|
| **Mentor application received, to the applicant** | The applicant | Someone applies to mentor |
| **Selected to mentor: the mentors' course** | The applicant | An Administrator presses **Select**. It carries the **Mentors' course invitation link**. |
| **More information asked of a mentor applicant** | The applicant | An Administrator presses **Ask for more information** |
| **Mentor application declined** | The applicant | An Administrator presses **Decline** |
| **New mentor application, to Administrators** | Administrators | An application passes every check |

The area **Student registrations** covers the student registration form.

| Email | Who gets it | When |
|---|---|---|
| **Student registration received, to the student** | The student | The registration is written to Airtable |
| **Student registration from an address with an account** | That address | The address already has an account on the site. It carries the sign-in and password reset links. |
| **Student registration from an address already registered** | That address | The address is already registered |

The area **Student interests** covers the student interest form.

| Email | Who gets it | When |
|---|---|---|
| **Student interest received, to the student** | The address the student gave | The interest is stored as new or held. The representative the student names gets nothing. |
| **New student interest, to Administrators** | Administrators | The interest passes every check. A held or spam interest sends none. |

The area **Quiet students** sends mail only while **Daily list** is *On* (`Settings`, tab **Students and mentors**, section **Quiet students**). *Preview* mails nobody. A student is quiet after **Days of quiet** with no sign-in, visit, call or report: 7 to 60, default 14.

| Email | Who gets it | When |
|---|---|---|
| **Quiet student reminder, to the student** | An active student on a track | Once for each stretch of quiet, with no call booked ahead. Never for a test institution's student. |
| **Quiet students, to the mentor** | The mentor | At most once a day, listing the students just reminded |
| **Quiet students this week, to the Administrator** | Each Administrator with a quiet student at an institution assigned to them | The first daily run of each Monday |

Reply addresses:

- Some emails reply to **Contact address in emails**, while it holds an address. They are the emails to mentor applicants, the student registration emails and **Student interest received, to the student**.
- A **Mentor calls** email replies to the other person on the call. The mentor's copies of a group session moved, canceled or reminded have no reply address.
- Some reply to the Administrator who acted. They are a question to an applicant, an agreement returned with a note, and a sponsor's agreement taken out of force. So are an event returned or canceled with a note.
- A colleague's invitation replies to the inviter. A code problem replies to the person who reported it.

Editing an email:

- **Edit** opens the email's editor: a **Subject**, a **Body**, a table of **Placeholders** and a **Preview** filled with example values.
- A placeholder is a word in braces, such as `{set_password_link}`, replaced by its value when the email is sent. The table says what each means, and marks the ones the email needs as **Required**.
- A sentence whose placeholders are all empty is left out. So is a line left empty, with a line just above it that ends in a colon and holds no placeholder.
- Times in call and event emails are on the recipient's own clock, or the site's for an address with no account. Counts arrive in words, such as "3 sessions".
- An edited email goes to everybody in the words you wrote, whatever their language. An email not edited reaches each person with an account in their own language.
- An email with two versions, such as the invitations with and without a sender named, is edited one version at a time.

The buttons in the editor:

- **Save** stores the subject and the body. Saving the shipped text unchanged stores nothing.
- **Send me a test** sends the email as saved, filled with example values, to your own address only. Its subject starts with the word Test in brackets. The Log marks it *Test*. Save your typing first, or nothing is sent.
- A test carries no calendar file and no reply address. The real email adds them.
- **Restore the original** appears once the email is edited. It asks first, then drops your edit.

| State | What it means |
|---|---|
| *Shipped text* | Sent as the plugin ships it. |
| *Edited* | Sent as an Administrator wrote it. |
| *Edited, and the shipped text has changed since* | An update changed the shipped text. Your edit is still sent, and the editor shows the new shipped text to compare. Saving again clears this. |
| *Edit set aside: ...* | The edit has a placeholder the email no longer has, or lacks one it needs. Or its subject has one that goes in the body. The shipped text is sent until you save the edit again. The state names the placeholder. |

**Save** is refused when:

- the subject or the body is empty;
- a required placeholder is missing;
- a placeholder that goes in the body alone is in the subject: links that carry a secret, a meeting link, and words somebody typed;
- a placeholder is not one of the email's;
- WordPress would remove part of the text;
- the subject is longer than 200 characters or the body longer than 10,000;
- someone changed the email, or restored it, after you opened the page.

After a refused save, nothing is stored and the editor keeps what you typed. The notice on the screen names the problem, such as *Nothing was saved: this email needs {when}.*

A subject made only of placeholders is saved. If they all come out empty, the email goes out with its shipped subject.

Step by step: [[Change the words of an email and test it]]; [[Undo a change to an email]]; [[An email edit was refused]]; [[Choose who is emailed about institutions, mentors and students]].

#### Signature and footer

The block every email the plugin sends ends with, after a line holding `-- `. It is on `WPCredits Program > Tools > Emails`, tab **Signature and footer**. The emails WordPress and the Two Factor plugin send themselves do not get it.

It ships as four lines:

- `WordPress Credits, WordPress Education`
- `{site_link}`
- `{dashboard_link}`
- `This email comes from the WordPress Credits program. Questions? Write to {program_contact}.`

| Placeholder | What it becomes |
|---|---|
| `{site_name}` | The site's name. |
| `{site_link}` | The site's home page, the same for everyone. Empty if the email names it already. |
| `{dashboard_link}` | The person's own dashboard. Empty with no account or no program role, an unpublished page, or an email that names it already, as every invitation does. |
| `{program_contact}` | The address in **Contact address in emails**. Empty while that box is. |

- A line or sentence left with nothing is left out whole.
- The dashboard is the Student Report Card, Mentor Report Card, Institution Dashboard, Sponsor Dashboard or Administrator Dashboard, by who the person is.
- The preview and **Send me a test** show the footer as an Administrator gets it. A note under the preview lists the dashboard of each kind of person whose page is published.
- An empty block is not saved, nor one over 10,000 characters.

Step by step: [[Change the signature and footer of every email]]; [[Undo a change to an email]].

#### Settings

The Emails tool's one setting, at the top of the tab **Signature and footer**, saved with **Save settings**.

- **Contact address in emails**: the mailbox every footer names for questions. Left empty, the footer leaves that sentence out. Only one email address is taken; anything else is refused and the saved one kept. Default: empty.

The mentor's invitation also uses this address for replies.

Step by step: [[Set the contact address the emails name]].

#### Log

Every email the site sent in the last 30 days: the plugin's, WordPress's (a password reset, for example) and the Two Factor plugin's sign-in codes. It is the tab **Log** of `WPCredits Program > Tools > Emails`. Each row is one recipient, Cc and Bcc included.

| Column | What it shows |
|---|---|
| **When** | Date and time, in the site's time zone. |
| **To** | The address. With an account, the name under it links to the person's row on their screen's **Accounts** tab, or to the user editor with no program role. |
| **Recipient type** | *Student*, *Mentor*, *Institution member*, *Sponsor member*, *Administrator*, *Applicant (no account)* or *Other*. |
| **Area** | What sent it, such as *Invitations*, *WordPress* or *Two Factor*. |
| **Email**, **Subject** | Which email, and its subject. |
| **Status** | See below. A **Test** mark shows a test you sent yourself. |

| Status | What it means |
|---|---|
| *Handed to the mail server* | The site passed it on. A full mailbox or a spam filter can still stop it. |
| *Failed* | WordPress could not hand it over. The reason is under it. |
| *Not confirmed* | Another plugin took it over, or the request ended before WordPress said. |

Find a row with **Search address, name or subject**, or the filters **Area**, **Recipient type**, **Status**, **Email** and **When**, then **Filter**. **Status** has a fourth choice, **Test**. **Email** takes a few letters of the email's name. **When** offers *Today*, *The last 7 days*, *The last 30 days* or *From and to dates*, with **From** and **To** boxes. Fifty rows a page, newest first.

- The Log never keeps an email's text, attachments, sign-in codes or password links.
- Rows older than 30 days are deleted every day.
- WordPress's **Export Personal Data** and **Erase Personal Data** include a person's rows, by address. The erasure's own confirmation email then shows as a new row.

Step by step: [[Find out what the site sent to someone]].

### Mentor Status Checker

`WPCredits Program > Tools > Mentor Status Checker` looks on WordPress.org profiles for the course set under **Course slug**. It checks, in this order:

1. The applications selected on this site, under *Selected (course pending)*, newest first. Where the course shows, the applicant is made a mentor, while **Make mentors from applications** is on (`WPCredits Program > Settings`, tab **Students and mentors**).
2. The Airtable mentors at the status under **Check mentors with status**, until **Stop checking mentors in Airtable** is ticked. Where the course shows, their status becomes the one under **Promote them to**.

**Run check (report only)** changes nothing in Airtable and makes nobody a mentor. **Run check and promote to Active** does both as it goes, after one question; its last word is the status under **Promote them to**. **Clear cached profile results** and **Clear results** empty the saved profile answers and the table.

| Outcome | What it means |
|---|---|
| *Awaiting promotion* | The course shows. **Promote** is offered. |
| *Waiting* | An application whose course shows, while **Make mentors from applications** is off. No **Promote**. |
| *Promoted* | The Airtable status was changed. |
| *Made a mentor* | A mentor on this site. A held invitation or a refused Airtable row is said beside it. |
| *Skipped* | Left unchanged: the Airtable status had moved, or the person is a mentor already. |
| *Failed* | Not promoted. The reason is beside it. |

- Without the Airtable connection, only the site's applications are checked, and a mentor made from one gets no Airtable row.
- If Airtable cannot be read, the run still checks the applications and says so above the results. With no application selected, the error is the whole answer.
- An application that has left *Selected (course pending)* since the run is refused: **Promote** says why, and **Promote all eligible** marks its row *Failed*.
- `wp wpcredits check_mentors` runs a report; with `--promote` it also promotes.

Step by step: [[The Mentor Status Checker: check, then promote]]; [[The course is completed: make the applicant a mentor]].

#### Settings

At the top of the checker's screen, saved with its own **Save settings**.

- **Check mentors with status**: the Airtable status a run reads. Typed when the statuses cannot be read. Default: *Vetted - positive*.
- **Mentors in Airtable**: the box **Stop checking mentors in Airtable**. Tick it once no Airtable mentor holds the status above. Default: unticked.
- **Promote them to**: the status a promotion writes. It needs the `data.records:write` scope on the Airtable token. Default: *Active*.
- **Course title**: a fallback signal only. Default: *WordPress Credits Mentor's Course*.
- **Course slug**: from the course's learn.wordpress.org address; the reliable signal. Default: `wordpress-credits-mentors-course`.
- **Completion phrase**: must be in the same history entry as the course. Default: *Completed the course*.
- **Profile history filter**: Choices: **Milestones only (faster)**, **All contributions**. Default: **Milestones only (faster)**.
- **Maximum history pages per mentor**: 1 to 100. Default: 15. A longer history reads *Could not check*, never *Not completed*.
- **Mentors per batch**: 1 to 25. Default: 3. **Delay between requests (ms)**: 0 to 5000. Default: 0.
- **Cache profile results for (seconds)**: 0 to 2592000 (30 days); 0 keeps nothing. Default: 43200 (12 hours). A failed read is always tried again.
- **Daily check**: **Run the check automatically once a day** and **Let the daily check also promote mentors**. Both off by default; the second needs the first.
- **Slack webhook**: where the message after a promotion goes; the webhook decides the channel. Leave it blank to keep the saved one, or type `remove`. Only a Slack incoming-webhook address is taken. The site posts each institution newly at *Confirmed* there too.
- **Channel to add them to**: named in the message, such as `#mentors`. Left empty, it says "the mentors channel".

A daily check that promotes writes to the shared Airtable base, and can make mentors and send their invitations.

To get a webhook: create a Slack app in the workspace, switch on Incoming Webhooks, add a webhook for the Administrators' channel, and paste its address.

#### The Slack message after a promotion

While a webhook is saved, each action that promotes or makes a mentor sends one message when it ends. That is a run (daily, **Run check and promote to Active** or `--promote`), **Promote all eligible**, **Promote**, or **The course is completed** on an application.

The message calls `@channel`, says how many moved, to which status and to which channel to add them, and lists each person:

- a link to their WordPress.org profile, and their Slack name when the profile shows one;
- *from an application on the site*, for an applicant;
- at the end, who promoted: *the daily check*, *WP-CLI* or the Administrator's name.

- Only people actually moved or made mentors are named, an applicant even if their Airtable row was refused.
- A message holds 25 people at most, in the site's language.
- A message Slack refuses is kept and sent with the next. Every run sends what is kept, so with the daily check on, a mentor waits a day at most.
- A promotion never waits on Slack. Now and then a person may be named twice.
- Promotions made with no webhook saved are never announced. Taking the webhook away drops those still waiting.

The line under the buttons says when the last message went and how many it named, or why it failed, and how many wait.

### Student Duplicate Finder

At `WPCredits Program > Tools`, **Student Duplicate Finder** lists students with more than one row in Students, Students Reports or Feedback in Airtable. It proposes which rows to delete, and deletes the ones you tick and confirm.

It does not stop duplicates being made. Stopping them at source is a change to the Airtable automation that makes the Students Reports and Feedback rows: see [Stopping duplicate students at the source](https://wordpresseducation.org/duplicate-students-at-the-source/).

Step by step: [[A student has duplicate rows]].

#### Delete duplicates here, not by hand in Airtable

The site stores Airtable record IDs, and Airtable cannot warn you that a row is one of them:

- a student's account holds their Students Reports row, which is how their report form is loaded and saved, and their Feedback row, for their surveys;
- a mentor's call note and a booked call hold the row of the student they are about;
- audit log entries hold the rows they describe.

Delete such a row in Airtable and the site points at nothing. The Student Report Card keeps the program it last saved, but its report form cannot load the row and says so. The next students sync moves the account to another row for the same address, if one is left.

The finder refuses to delete a row that anything on the site points at. It checks every site record that holds the record ID, and checks again just before it deletes.

#### What a scan does

A scan reads the three tables and writes nothing to Airtable.

- It runs every three hours, at a different time from the students, mentors and institutions syncs. The **Scan** card shows the last and the next.
- **Scan now** runs one at once. While it runs, **Cancel scan** takes its place and **Review selection** is grayed out.
- After two minutes with no progress, the card says so: press **Cancel scan** and start again.
- A scan that fails changes nothing: its error shows after **Last scan error:** and the last list stays. It fails when Airtable does not answer, a table returns no rows, or the site cannot read what points at the rows.
- Capitals and spaces around an address do not matter. A student whose rows spell it differently is flagged. A row with no address is not listed.

#### Working through the list

The tiles count the duplicated students, the **Ready** ones and the ones needing a decision, and, per table, the rows proposed and held.

- Under **Needs a decision**, each row that may go has its own box. Nothing is ticked for you.
- **Confirm the delete** also lists what it left out, and why. It leaves out a row that would leave a student with no row in a table.
- One confirmation deletes at most 100 rows.

Step by step: [[A student has duplicate rows]].

#### Why a row is held back

In each table the finder keeps the newest row. It proposes an older row only when nothing on it is worth keeping. Otherwise **Why** says what holds it:

| **Why** says, in short | What to do |
| --- | --- |
| The site points at it | Delete the other row. This one cannot be ticked. |
| The site uses an older row for this student | Keep the row the site uses. |
| Still at a live status | Find out why a live student has two rows. |
| Records a graduation | Keep it. |
| Holds work fields, or survey answers | Keep it, or copy the values to the row you keep. |
| Has Total hours set | Keep it, or move the total. |
| Has Notes in Airtable | Read the note first. |
| The newest row has no institution link, mentor, status or Course | Fill it in on the newest row first. |
| Created in the same second | Look at both and pick. |
| The newest row is at an ended status | Usually keep the older row, if it is live or records a graduation. |

- Live means a status in **Currently mentoring** (`WPCredits Program > Settings`, tab **Students and mentors**), or *Paused*. A graduation is *Graduate* or *Pending graduation*.
- Ended means *Not moving forward*, *Dropped out*, *SPAM*, *Duplicated* or *Fail*. Status rules skip Feedback.
- One held row anywhere makes the student **Needs a decision**.

#### What happens when you press Delete

The notice says how many rows went from each table and until when their copies are kept. Under **Left out, and not deleted:** it names each row kept back, and why.

- Every row is read and checked again. A row that is gone, that the site now points at, or that changed is left out. Changed means more work, answers or hours, a held value replaced, or a new reason to stay. If every row is left out, the notice says nothing could be deleted.
- The last row an address has in a table is never deleted.
- A sealed copy of every row is kept first. If one cannot be kept, or the site cannot encrypt, nothing is deleted. Nothing is deleted either while a scan runs, if Airtable cannot be read to check the rows again, or if the site cannot read what points at the rows.
- Rows go Feedback first, then Students Reports, then Students.
- One delete runs at a time. A second deletes nothing and says so. After a delete that was cut off, this lasts up to five minutes.
- If Airtable stops part of the way, the notice gives its message. Rows not deleted stay in the list.

#### Deleted rows, and the 30-day copy

**Deleted rows** lists what the finder deleted: **When**, **Who**, **Table**, **Record**, **Created**, **Status or Course** and **Copy**.

| **Copy** says | What it means |
| --- | --- |
| Kept until a date, with **View copy** | **View copy** shows the row's cells as they were. |
| Airtable did not confirm this delete | Once it is an hour old, the daily run asks Airtable. A row still there removes the entry; a row gone makes it an ordinary entry; with no answer, it waits for the next run. |
| Erased after 30 days | The cells are gone. The entry stays, but the list shows only the 50 newest erased entries. |
| Airtable never confirmed this delete | Look the record ID up in Airtable. If the row is there, the next scan lists it. |

There is no automatic restore. Type back by hand only what is needed: a Students row typed back can make the automation build a new report and feedback pair.

#### Settings

Deleting ships switched off. Its setting is in the **Settings** section at the top of the finder, with its own **Save settings**.

- **Deleting duplicates**: tick **Let administrators delete the duplicated rows they select and confirm** to allow deletes. Default: off.

While it is off, the list is read-only and **Review selection** is grayed out. Scanning works either way.

#### On the Administrator Dashboard

The **Duplicated students** card on `Administrator Dashboard > Watch` counts the students the last scan listed, and how many are ready, with **Open the Student Duplicate Finder**. With none, it says whether a scan has run. A **Duplicated students** tile shows the count, except at zero.

### Track Builder

Where tracks are made and changed, at `WPCredits Program > Tools > Track Builder`. A track is one Airtable status, one form on the Student Report Card and a key chip. It may follow a Learn course and have an hours target.

A track is a draft until you publish it, and nothing reaches students before that. The program's four original tracks are here too, already published.

#### The track list

{{image:track-builder-list|The track list: the program's four tracks, and a track of your own still a draft.}}

One row per track, oldest first, under **New track**.

| Column | What it shows |
|---|---|
| **Track** | The name, with its **Learn course** link and partners |
| **Status** | The Airtable status of its students, and the key |
| **State** | *Draft*, *Published*, *Unpublished changes* or *In the trash* |
| **Students** | Students the last students sync found on the status |
| **Last published** | When and by whom, or *Never* |
| **Actions** | **Edit**, **Duplicate**, **Preview**, **History**, then **Publish** on a draft or **Publishing** once live |

A line under **State** warns when the last compile left the track out: its students see the 150-hour form meanwhile. Another warns when a live track's status is missing from **Currently mentoring** in Settings.

**Delete** appears only on a track never published. It asks first and cannot be undone. A track published once is kept, because its columns stay in Airtable.

#### Starting a track

Two ways, both ending on the new track's page as a draft:

- **New track**: type the **Name**, **Airtable status** and **Key**, and the **Learn course** link if it follows one. With a link that resolves, **Name** may be left empty and is taken from the course. The form starts empty.
- **Duplicate**: copies a track's questions, the four original tracks' included. Type a name, status and key of its own. The copy has no course, hours target, partners or partners' narrative.

For both:

- No two tracks share a name, status or key, drafts included.
- A key is 2 to 20 lowercase letters, digits or hyphens. *sensei*, *paused* and *pending* are refused too.
- The chip color is the first one no other track holds.

Step by step: [[Start a new track]].

#### The track's page

The properties, then the questions.

- **Learn course**: `https://learn.wordpress.org/course/` and the course's name. The next row shows the course's title and number.
- **Hours target**: whole hours, or empty for none.
- **Key chip color**: type *blue*, *cyan*, *teal*, *green*, *red*, *pink* or *purple*.
- **Partners**: sponsors Approved at the Partner tier today, at most 10. A lapsed one stays ticked until you untick it.
- **Partners' narrative**: one text for all partners, up to 5,000 characters. Each partner's annual report starts with it, as last published.

{{image:track-builder-track|A track's page: its properties, then its form, group by group.}}

- **Save the track** saves the properties. Questions save as you add, edit and move them.
- A published track keeps its status and its key.
- If another Administrator saved the track after you opened it, your Save is refused and your typing kept.
- **Read the course again** asks Learn afresh. Otherwise the site keeps Learn's answer for a day.
- While Learn cannot be reached, a new course link is not taken. Press **Save the track** again later.
- A new course link matches each question's lesson to the new course by heading. The notice names the questions left with no lesson.

**Questions** has the Student Report Card's four groups: **Total hours**, **Onboarding**, **Project** and **Wrap-up**. **Total hours** holds only the Hours question, on the column *Hours*. With no Learn course, students see the hours box in its own section, **My hours**.

With a course, each group lists the lessons of the module with its name, such as *Lessons on Learn: 3 of 13 have questions.* A lesson with no question yet offers **Add a question under this lesson**. For a lesson that has questions, choose it in **Under lesson** in the group's add row: the question goes after the lesson's last one. A lesson's first question takes the lesson's title as its heading.

A line above the questions says what publishing would create, such as *Publishing will create 3 columns in Airtable.*

Step by step: [[Add, move or remove a question]].

#### Controls and columns

Ten controls: **Text, one line**, **Text, many lines**, **Rich text**, **Web address**, **Email address**, **Number**, **Checkbox**, **One choice of several**, **Screenshot** and **Contribution team**.

- The control sets the Airtable column's type. A column already in Airtable must have that type.
- **Contribution team** writes *Main Contribution Team* and never creates a column.
- One column holds one question on a track.

A column shared with another track, as after **Duplicate**, is named on the question's page. Changing the words keeps it shared. Changing the control or a select's choices gives the question a column of its own, named after the track. On a question nobody shares, a new control keeps the column.

#### One question

A question's own page: the **Airtable column** and **Control**, then the rows for that control. **Save the question** returns to the track.

{{image:track-builder-question|A question's page: its column, its control, then the rows for that control.}}

| Row | What it does |
|---|---|
| **What the student reads** | The label above the box |
| **Group** | **Onboarding**, **Project** or **Wrap-up**; **Total hours** for Hours only |
| **Help under the box** | A sentence under the control |
| **Heading before it** | A heading before the question |
| **Learn lesson** | The lesson it reports on, or *None* |
| **Subheading before it** | A second heading, shown when it changes |
| **Note after the run** | A sentence after the question |
| **Row**, **Shares one column of its row** | Same row name: side by side; ticked ones stack |
| **Marked required** | Shows *Required*; nothing is enforced |
| **Kept off everything an institution reads** | Off the school's view and export; always for an email |
| **Filled in by the program: students see it but cannot change it** | Shown as text; a save never sends it |
| **Lowest value**, **Highest value**, **Step** | A number's limits; the step sets a new column's decimals |
| **Length limit** | **Text, one line** only |
| **Monospace, for code** | **Text, many lines** only |
| **Choices, one a line** | An existing column must offer every one |
| **Developer note** | Why a column name looks odd; students never see it |

**Filled in by the program: students see it but cannot change it** is not offered on Hours, **Screenshot** or **Contribution team**.

Once published, a question keeps its column, control and choices. Its words can change. To ask it differently, remove it and add a new question.

Step by step: [[Show a certificate code on the Student Report Card]]; [[Change a track that is live]].

#### Preview and History

**Preview** draws the draft's form as a student sees it, empty. Nothing typed there is kept.

{{image:track-builder-preview|Preview: the form a student on the track fills in, and nothing you type there is kept.}}

**History** has three parts:
- **What publishing would change**: the published copy against the draft.
- **Saves**: newest first, at most 20, each with who, when and what changed. Saves are kept only while WordPress revisions are on, and never more than the site's revision limit.
- **Publish log**: publishes, unpublishes, columns created and checklist ticks, with who and when.

{{image:track-builder-history|History: what publishing would change, every save, and the publish log.}}

#### Publishing

**Publish** or **Publishing** opens the publish screen. It reads the Airtable base first. Nothing changes until you press the button at the bottom.

{{image:track-builder-publish|The publish screen: the columns it will create, the three steps only you can take in Airtable, and the box for the track's name.}}

| Part | What it holds |
|---|---|
| **This track cannot be published yet:** | What stops it. There is no button meanwhile |
| **Worth knowing before you publish:** | Warnings that do not stop it |
| **Columns** | Each column to create, with its type and choices |
| **What the site cannot do** | Three steps in Airtable, each with **I have done this** |

What stops a publish:
- A name, status or key another track holds, drafts included.
- A status that is another track's name, or that already means something else to the program, such as finished or paused.
- The Hours question outside **Total hours**, or another question inside it.
- A partners' narrative over 5,000 characters, or any other rule a Save also checks.
- A column with a question's name but another type, or missing some of a select's choices.
- A computed column, or a link column, unless the question is **Contribution team** and the column is *Main Contribution Team*.
- A **Contribution team** question while the base has no *Main Contribution Team* column.
- More than 500 columns on the table afterward.
- A base the site cannot read, or a **Students Reports table** setting that is not a table ID.
- Columns to create while no **Schema token** is set (`WPCredits Program > Settings`, tab **Connection**). This shows under **Columns**, not under **This track cannot be published yet:**, and the button is withheld. Create the listed columns in Airtable instead.

Warnings: a Status choice that nearly matches the status, a course link that does not resolve, and more than 450 columns.

The three steps are **Add the status to the reports automation**, **Create the welcome email automation** and **Add the two Status choices**. An unticked step never stops a publish. Until the first is ticked, the institution import does not offer the track.

- The button reads **Publish this track** on a draft and **Publish the changes** while the State reads *Unpublished changes*.
- With columns to create, type the track's name first. Capitals count. The site can never remove a column it created.
- If the columns to create changed since the page was drawn, nothing is published and the screen comes back with the new list.
- Publishing adds the status to **Currently mentoring** in Settings.
- A save made while it runs is not published: the track shows *Unpublished changes*.
- If Airtable refuses part-way, the notice gives Airtable's message. Press the button again: it creates only the missing columns.
- After a page that timed out, Publish on every track says another track is being published, until up to 20 minutes have passed.

On a live track the screen also offers **Check it against Airtable** and, except on the four original tracks, **Take it off the live site**. **Check it against Airtable** reads the base again and says whether every column is still there with its type, and the status is a choice on both Students Reports and Students. It changes nothing. Taking it off is refused while students hold the status. It leaves Airtable and **Currently mentoring** as they are.

Step by step: [[Publish a new track]]; [[Change a track that is live]]; [[Take a track off the live site]].

#### The four original tracks

*WordPress Credits Program 150h*, *WordPress Credits Program 50h*, *Developer Track* and *Designer Track*. You edit, duplicate, preview and publish them like any track.

- No other track can take their statuses, keys or names.
- They cannot be deleted or taken off the live site. Edit one and publish the change instead.
- A new site creates and publishes all four by itself, checklists ticked.
- If one is missing, `wp wpcredits seed_tracks` puts it back.

### Need help?

A question box answered from the WordPress documentation, at `WPCredits Program > Tools > Need help?`. Below **Settings** come **How answers are produced** and **Try a question**, which shows exactly what a person would get.

People ask from **Need help?** in the site header, and from **Resources** on the Mentor and Student Report Cards and the institution page, where **Who can ask** allows it and a provider is set.

Step by step: [[Set up Need help?]].

#### Settings

At the top of the screen, with its own **Save settings**. Each question goes to the provider's company. Nothing is stored on this site.

- **Need help?**: the box **Switch it on**. Off, nobody gets an answer, the header button goes and its page is unpublished. Default: on.
- **Answer provider**: Choices: the first one, which starts with *None*, and **Google AI Studio (Gemini)**. Default: the first, which answers nobody.
- **Provider API key**: only its last four characters are shown afterward. Leave it blank to keep the current key. A Gemini key is free at aistudio.google.com.
- **Model**: typed. Default: `gemini-flash-latest`. Leave it as is.
- **Who can ask**: Choices: **Mentors and administrators**, **Students, institutions and sponsors as well**, **Anybody logged in to this site**, **Administrators only**. Default: **Mentors and administrators**.
- **Questions per person per hour**: 0 to 200, where 0 removes this limit. Default: 20.

Limits and rules:

- Nobody logged out can ask.
- The whole site gets at most 300 answers a day.
- Past a limit, people are asked to come back shortly.
- **Where answers come from** lists the four wordpress.org sites the provider searches. It has nothing to set.
