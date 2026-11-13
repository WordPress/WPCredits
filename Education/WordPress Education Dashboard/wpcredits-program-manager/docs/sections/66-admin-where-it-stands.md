## Where does it stand?

Each table follows one path from first contact to the end. Find the words you see, then read what they mean and what to do next.

### Where a school stands

A school's state shows on the Administrator Dashboard's **Overview** cards, on `Institutions > Pipeline` and, for you alone, at the top of its Institution Dashboard.

| What you see | What it means | What to do next |
|---|---|---|
| *new* on its row in **Institution applications** (**Overview**), with **Send the confirmation link again** | The school applied. The contact has not confirmed the address yet, so **Approve** is refused. | See [[The application's address is not confirmed]]. |
| *held by the anti-spam checks* in **Institution applications** | The form's checks held it. | Read which checks on `Institutions > Waiting for review`, then see [[A school applied]]. |
| *new* in **Institution applications**, with no **Send the confirmation link again** | The address is confirmed. The application waits for your decision. | See [[A school applied]]. |
| *waiting on the applicant* in **Institution applications** | You sent a question. The answer comes to your email. | Read the answer, then see [[A school applied]]. |
| "Approving this application stopped partway" in **Institution applications** | **Approve** made the Airtable record, and maybe the account, then stopped. | See [[Finish an approval that stopped halfway]]. |
| *No stage*, under **Not counted above** on `Institutions > Pipeline` | The school was entered in Airtable with no Current Stage. It is not an application. | See [[A school came in through Airtable, not the site's form]]. |
| A count beside **Institutions with no live member** on `Institutions > Pipeline` | Nobody at these schools can act for them on the site. | See [[Give a school its first account]]. |
| *Not started* in the **Agreement** column of `Institutions > Pipeline`; on its Institution Dashboard, "This institution's agreement is not settled: not started." | The school has not generated or uploaded its Collaboration Agreement. | Wait for the school, or see [[Record an agreement the program already holds]]. |
| *Template generated* in the **Agreement** column | The school generated the template and has not uploaded a signed copy. | Wait for the upload. |
| **Awaiting review** on the **Collaboration Agreements** card, *Overdue* once it is late | A signed copy waits for you. | See [[A signed Collaboration Agreement arrived]]. |
| "1 returned, waiting for a new upload" on the **Collaboration Agreements** card; *Returned* in the **Agreement** column | You returned the copy with a note. The school must upload again. | Wait for the new upload. |
| *Accepted* or *On file* in the **Agreement** column; "Accepted on the site" or "Recorded on file" under **Agreements in force** on `Institutions > Agreements` | The site holds an accepted agreement. It is in force once Airtable also records it as Accepted or On file, and then the school can enroll students and invite colleagues. If Airtable does not agree, the Institution Dashboard says so and the school's members see only the Collaboration Agreement card. **Agreements in force** lists only agreements in force. | See [[Enroll students for a school]] and [[Invite a representative or a tutor for a school, and keep its tutor list]]. |
| "3 students now" on its row in `Institutions > Pipeline` | The school has students on the program. | Follow its semester reports. |
| A row under **Due for drafting** or **To review** on the **Semester reports** card | A finished semester has no report yet, or a draft waits for you. | See [[A semester report: draft, review, approve, reopen]]. |
| "1 revoked agreement" on the **Collaboration Agreements** card, with **Reinstate**; *Revoked* in the **Agreement** column | The agreement was taken out of force. The school's members see only the Collaboration Agreement card. | See [[Take an agreement out of force, or put it back]]. |

### Where a student stands

A student's state shows on the Students screen in wp-admin, in its **Registrations**, **Interests** and **Accounts** tabs, and on the Administrator Dashboard.

| What you see | What it means | What to do next |
|---|---|---|
| *New* or *Held* on `Students > Interests` | A student asked about a school that is not in the program. | See [[A student is interested in a school that is not in the program]]. |
| **Waiting to be written**, with the reason, on `Students > Registrations` | The registration is not in Airtable yet. The reason says why, and what the site does next. | See [[A student registered]]. |
| **Could not be written** on `Students > Registrations` | The site could not write the registration to Airtable for 72 hours. The line shows the last error. The site keeps trying. | See [[A student registered]]. |
| **Written to Airtable** on `Students > Registrations` | The student has a row in Airtable. The student is emailed that the registration arrived, unless the day's email limit was reached; the row says when the email was not sent. | See [[A student registered]]. |
| **Already has an account** or **Already registered** on `Students > Registrations` | The same person is already known. Nothing new was written. | See [[Find a student]]. |
| *A student to add* in **Requests from institutions** (**Overview**) | A school enrolled this student on its Institution Dashboard. | See [[A school's other requests (a student to add, a mentor wanted)]]. |
| The student in the **Never invited** view of `Students > Accounts` | The student has an account. No invitation has been sent yet. | See [[Invite a student, or several]]. |
| The student in the **Invited** view of `Students > Accounts` | The invitation went. | Nothing, unless the student cannot sign in. |
| "-" in the **Mentor** column of `Students > Accounts` | Airtable gives the student no mentor. | Give the student a mentor in Airtable. The site shows it after the next students sync. |
| A track's name in the **Status** column of `Students > Accounts` | The student is on that track. | Nothing. |
| The student's row on **Students gone quiet** (**Watch**) | Nothing on record from the student for the days in **Days quiet**. | See [[Students who have gone quiet]]. |
| The student counted on **Duplicated students** (**Watch**) | The student has more than one row in Airtable. | See [[A student has duplicate rows]]. |
| *Paused* or *Pending graduation* in the **Status** column | The status comes from Airtable. The student is on no track, so **Students gone quiet** leaves them out. | Nothing on the site. The status is changed in Airtable. |
| *Graduated* or *Dropped out* in the **Status** column | The student finished or left the program. | Nothing. |

### Where a mentor stands

An applicant's state shows on `Mentors > Waiting for review`, in its views. A mentor's state shows on the **Sync**, **Accounts** and **Engagement** tabs of the same screen.

| What you see | What it means | What to do next |
|---|---|---|
| *address not confirmed* on a row of the **Waiting** view | The applicant has not followed the link in the acknowledgement. **Select**, **Ask for more information** and **Decline** wait. | See [[The applicant's address is not confirmed]]. |
| *acknowledgement not sent* on a row of the **Waiting** view | The acknowledgement email did not go. | See [[A mentor applied]]. |
| *held* on a row of the **Waiting** view | The form's checks held the application. | See [[A mentor applied]]. |
| A row in the **Waiting** view with none of these marks | A new row with no mark waits for your decision. A row you asked for more information, or marked for a second opinion, also appears in **Waiting** and is listed again in its own view. | See [[A mentor applied]]. |
| "Asked for more information on" a date, in the **Asked for more information** view | You asked the applicant a question. | Wait for the answer, then see [[A mentor applied]]. |
| A row in the **Second opinion** view | An Administrator marked the row for a colleague to read, with a note. Nobody is told by the site. | See [[A mentor applied]]. |
| "Checked on" a date, "the course does not show on the profile yet", in the **Selected (course pending)** view | You selected the applicant. The course is not done. The row tells the day it lapses. | See [[The Mentor Status Checker: check, then promote]]. |
| "Checked on" a date, "the course shows on the profile", in the **Selected (course pending)** view | The course is done. | See [[The course is completed: make the applicant a mentor]]. |
| "Making mentors from applications is off until the launch: this applicant waits." | **Make mentors from applications** is off on `Settings > Students and mentors`. | Ask the Administrator who runs the site. |
| "Declined on" or "Filed as spam on" a date, under **Recently decided** | You declined it, or it was filed as spam, by you or by the form's checks. | See [[Bring back a declined, lapsed or spam application]]. |
| "Lapsed on" a date, in the **Lapsed** view | The course did not show in time, or the applicant withdrew. | See [[Bring back a declined, lapsed or spam application]]. |
| "Made a mentor on" a date, in the **Activated** view | The applicant has a mentor account, and the invitation was queued. | Nothing. |
| "No invitation has gone since this applicant was made a mentor" in the **Activated** view | The invitation is held. The row names why: the account was matched by WordPress.org username alone, or another account also answered for the applicant, or no reason is given. | See [[A held invitation, and an Airtable row that was refused]]. |
| *Awaiting promotion* on `Tools > Mentor Status Checker` | The course shows on a mentor's profile, and the mentor is not promoted yet. | See [[The Mentor Status Checker: check, then promote]]. |
| A row under **Needs confirming** on `Mentors > Sync` | The mentors sync found an account with a mentor's WordPress.org username and nothing else, so it linked nothing. | See [[A mentor's account waits under Needs confirming]]. |
| *Active* in the **Status** column of `Mentors > Accounts`, in the **No students** view | The mentor is active and has no students. | Students are given a mentor in Airtable. See [[Read the cohort timeline: do we need more mentors?]]. |
| *Not set* or *Saved, no hours* in the **Schedule** column of `Mentors > Engagement` | The mentor has no weekly hours, so students cannot book a call. | See [[Find mentors who have gone quiet or have no hours]]. |
| *Not in Airtable* in the **Status** column of `Mentors > Accounts` | Airtable does not hold the mentor as Active. The mentor's student list is empty. | Nothing on the site. A mentor's status is changed in Airtable. |

### Where a sponsor stands

A sponsor's state shows on the Administrator Dashboard's cards and on the Sponsors screen in wp-admin (**Sponsors and offers** in the dashboard's menu), in its **Sponsors**, **Accounts** and **Agreements** tabs.

| What you see | What it means | What to do next |
|---|---|---|
| *new* or *held by the anti-spam checks* on its row in **Sponsor applications** (**Overview**) | The company applied. A held one was stopped by the form's checks. | See [[A company applied to sponsor]]. |
| *waiting on the applicant* in **Sponsor applications** | You sent a question. | Read the answer, then see [[A company applied to sponsor]]. |
| "The approval of this application is half done" in **Sponsor applications** | **Approve** made the sponsor record, then stopped. | Press **Approve** again. See [[A company applied to sponsor]]. |
| "The site already holds a sponsor with this name or website." in **Sponsor applications** | Approving would make a second record. | See [[A company applied to sponsor]]. |
| *In review* in the **Status** column of `Sponsors > Sponsors` | The status a sponsor added by hand starts with. Only an *Approved* sponsor can have an account. | See [[Add a company that never applied]]. |
| The sponsor in the **No account** view of `Sponsors > Accounts` | The sponsor is *Approved* and nobody can sign in for it. | See [[Give a sponsor an account, and manage its people]]. |
| "This site: nothing on file." on `Sponsors > Agreements` | No agreement is held. A sponsor works without one. | See [[A sponsor's agreement: record, take out of force, put back]], if one exists. |
| *Waiting for review* on the **Sponsor Collaboration Agreements** card (**Overview**) | The company uploaded a signed copy. | See [[A sponsor uploaded its signed agreement]]. |
| "This site: the last document was returned." on `Sponsors > Agreements` | You returned the copy. | Wait for a new upload. |
| "This site: an agreement is accepted" or "the program holds a signed copy, recorded by hand" | The agreement is in force. | Nothing. |
| *A new offer* or *Changes to a live offer* on the **Sponsor offers to review** card | The sponsor sent an offer for review. | See [[A sponsor sent an offer for review]]. |
| A row on the **Sponsor posts to review** card | The sponsor sent a post for review. | See [[A sponsor sent a post for review]]. |
| A row under **Waiting for the program's OK** on the **Events** card | The sponsor planned an event. The card shows only while **Sponsor tiers** is on. | See [[A sponsor planned an event]]. |
| The offer on **Offers running low** (**Watch**) | The offer holds fewer codes than its warning level. | The offer's name opens the sponsor's Sponsor Dashboard. See [[Open a sponsor's Sponsor Dashboard]]. |
| The sponsor on **Sponsor terms ending** (**Watch**) | Its term ends within sixty days. | Press its name to open its Edit form, where **Term ends** is set. See [[Tiers: launch, grace, extra events]]. |
| A row under **Terms ended with no report** or **Drafts to finish** on the **Sponsor reports** card | A term ended and its report is not final. | See [[A sponsor's annual report]]. |
| **Revoked** under **Out of force** on the **Sponsor Collaboration Agreements** card; "This site: the accepted agreement was taken out of force." | The agreement was taken out of force. | See [[A sponsor's agreement: record, take out of force, put back]]. |
| *Paused*, *Not Moving Forward* or *Rejected* in the **Status** column | The sponsor is no longer *Approved*. Its accounts keep their access, unless **Its accounts** on `Settings > Sponsors` is set to remove them. Live offers do not stop by themselves. | See [[Pause or stop a sponsorship]]. |
