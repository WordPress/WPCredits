# Student registration on the site (design, 5 November 2026)

Approved by the product owner on 5 November 2026 ("Only confirmed, write the plan"). Plugin: WPCredits
Program Manager. Next free version when this was written: 1.123.54.

## 1. Why

Students join the WordPress Credits Program through an Airtable form, "WordPress Credits - Student
Registration Form" (share link `shrnGtYh2MFPgPOPm`, form view `viwSn34EJhzg65wFU` on the Students
table). The program is leaving Airtable step by step: sponsors, institutions and mentors already
apply on the site. The product owner decided on 5 November 2026 that student registration moves to
the site too, and that its link replaces the Airtable form wherever it is shared.

This is the first step only. The form on the site writes the student's row into Airtable's Students
table exactly as the Airtable form does, so everything after the registration (a program manager
assigning the mentor and the track, the welcome email, the site's account and invitation) works as
it does today. The Airtable migration later moves the rest onto the site.

## 2. The product owner's decisions (5 November 2026)

1. The form asks exactly the Airtable form's questions: the same thirteen, the same words, the same
   order. Nothing is added. (A track question was considered and dropped: the program manager
   assigns the track, and a requested track would need a new Airtable column.)
2. A registration goes straight into Airtable's Students table, as today.
3. No second Students row. Before writing, the site checks the email; when the address belongs to an
   account on the site, nothing is written and the student is directed to sign in.
4. The institution question offers only the institutions whose `Current Stage` is `Confirmed` (asked
   after the builder check, answered the same day). The stages stay a setting, `Confirmed` alone by
   default, so a later change needs no release.

The rest of this document is the author's account of how, played back to the product owner and
confirmed ("Looks right, write the design"), and the builder's calls marked as such.

## 3. The form

A public page at `/student-registration/` (the page the plugin ensures, as it does for
`/become-a-mentor/` and `/institution-application/`), with a shortcode and a block, off until
switched on (section 7). The page starts with the Airtable form's introduction, which the page owns
and an Administrator may edit:

> Welcome!
>
> Please fill out this short form to express your interest in joining the program. It only takes a
> few minutes and helps us better understand your background and availability.
>
> If you have any questions before submitting your interest, feel free to contact us at
> education@wordpressfoundation.org

The questions, in the Airtable form's order. Labels and help are the Airtable form's words, with its
two en dashes written as plain hyphens (the site's rule). Each answer goes to the Students column
named.

| # | Label | Help | Required | Answer | Students column |
|---|---|---|---|---|---|
| 1 | Full Name | | yes | one line of text | `Full Name` |
| 2 | Email | | yes | an address | `Email` |
| 3 | Educational Institution | | yes | one of the partner institutions (section 3.1) | `Educational Institutions` (link) |
| 4 | Department / Area of Study | | yes | one of nine choices (below) | `Your field of study` (single select) |
| 5 | Tutor Name - The professor or main contact person at your educational institution | If your educational institution doesn't require you to indicate a tutor, you can skip this question. | no | one line of text | `Tutor ` (the column name ends in a space) |
| 6 | Your tutor's email address | | no | an address | `Tutor email` |
| 7 | Preferred Start Date | | yes | a date | `Start Date` |
| 8 | Estimated End Date | | yes | a date | `End Date` |
| 9 | Total Expected Hours (Approximate) | Format: Decimal | yes | a number, one decimal place | `Total hours` |
| 10 | Languages You Speak | Select as many as you speak. | yes | one or more languages | `Languages` (link) |
| 11 | Preferred Language | Select only one. | yes | one language | `Prefered language` (link; the column is spelled so) |
| 12 | Do you have any accessibility needs we should be aware of? | Optional - Leave blank if none. | no | free text | `Accessibility needs` |
| 13 | Compliance declaration | I understand that the course is conducted in English, requires written communication, and involves proactive, independent remote work. | yes, ticked | a box | `English and proactivity acceptance` (checkbox) |
| 14 | Privacy Policy Compliance | By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://wordpress.org/about/privacy/). | yes, ticked | a box | `Privacy Policy Compliance` (checkbox) |

Thirteen questions and the privacy consent, as the Airtable form has them. The nine choices of
question 4 are the `Your field of study` column's, in its order: Technology & Engineering; Design &
Creative Media; Languages, Communication & Writing; Business, Marketing & Management; Education &
Learning; Natural Sciences & Mathematics; Health & Medicine; Arts & Architecture; Humanities & Social
Sciences.

The Airtable form's date help ("Date format: dd/mm/yyyy") is left out: the site uses the browser's
own date field, which shows the date in the reader's own format. (Builder's call; it changes no
answer.)

`Status` is left empty, as the Airtable form leaves it. The students sync makes accounts only for rows
whose `Status` is one of the statuses it reads (`student_statuses`, and each published track's own),
so a registration never makes an account or sends an invitation by itself.

**The columns.** The names and types above were read from the live base on 5 November 2026. They go
into the students sync's field map (`WPCPM_Students_Sync::fields()`, settings-driven, as the mentor
row's columns went into `WPCPM_Mentors_Sync::fields()`), and a test asserts them against a fixture of
that read. A column the table no longer has, or one of a type the site does not write, is left out of
the row and named on the registration, as the mentor activation does; the rest is written. The two
boxes are written ticked (`true`), since the form takes no registration without both.

**The page's own words.** The introduction is the page's content above the form block, and the
thank-you sentence is the block's own setting, as `thanks` is on the mentor form; both are the page's
to edit. The privacy consent keeps the Airtable form's words, which link the WordPress.org privacy
policy, the same policy the site's other forms link.

### 3.1 The institution question

One field to type and pick, as the site's other long lists are (the country on the institution and
mentor forms). It offers the program's partner institutions: the Institutions rows the site already
holds from the institutions sync (every three hours) whose `Current Stage` is one of the stages set in
Settings, `Confirmed` alone by default (decision 4). A new partner appears after the next
sync. An institution whose name starts with "TEST" is never offered, whatever its stage, so a test
institution made for testing the Institutions module stays off the public form (the author's call,
added with the plan). While the site holds no institution in those stages, the page says registrations are closed for
now, as it does with the switch off.

Under the field, one line for a student whose institution is not listed: "Is your institution not on
the list? Ask a faculty member or your administration office to contact us, or fill in the [student
interest form](https://airtable.com/appIzQKfwTn5dyPVp/shr6Gf1mMMEAxevw4)." The Students Interest Form
stays on Airtable; replacing it is not part of this design.

### 3.2 The languages

Questions 10 and 11 offer the names of the rows of Airtable's Languages table, read and cached as the
mentor activation reads its linked tables (`WPCPM_Mentor_Airtable_Row::link_names()`), A to Z. The
preferred language must be one of the languages ticked in question 10; the form says so when it is
not. (Builder's call: the Airtable form does not enforce it, and a preferred language the student
does not speak is a typing mistake.)

### 3.3 The checks on the answers

As the site's other forms check theirs: a real address in questions 2 and 6; the end date on or after
the start date; the hours a number from 1 to 2,000, kept to one decimal place; text cut to the
column's sensible length; and the answers cleaned as `WPCPM_Institution_Import::clean_row()` cleans
an imported student (no spreadsheet formula, no control characters). A refused answer is shown under
its question, with every other answer kept in its box.

## 4. What happens when a student sends the form

In this order:

1. **The guard.** `WPCPM_Form_Guard`, as on the other three forms: the hidden field, the signed token
   with its least time on the page, thirty submissions an hour per network address (the product owner,
   5 November 2026: a whole class on one campus network; the other forms keep five), the site-wide
   ceiling, and
   the consent recorded with what was agreed to. A submission the guard catches is kept as spam
   (section 6) and goes nowhere: no Airtable row, no email.
2. **The account check.** When an account on the site has the address as its email (any role,
   compared trimmed and in lower case), nothing is written to Airtable. The student is sent the
   "already has an account" email (section 5), which gives the sign-in link and the password reset
   link. This check needs no Airtable, so it is made first. A mentor or an Administrator who registers
   is answered the same way, as the product owner decided: no second row, sign in.
3. **The existing registration check.** When the site already holds a registration for the address
   that is waiting or was written (section 6), or a Students row has it, nothing is written either:
   a student who registered before and has no account yet. They are sent the "already registered"
   email. The Airtable lookup asks only for the matching rows
   (`LOWER(TRIM({Email})) = '<the address>'`, the address trimmed, lower-cased and escaped), so there
   is no paging to miss; an address is compared as typed, with no rewriting of dots or plus signs.
4. **The row.** Otherwise one Students row is written with the answers of section 3, through
   `WPCPM_Airtable::create_records()`, shaped by each column's type as the mentor activation shapes
   its row: links by the linked table's record IDs, choices by name, no typecast. A language or
   institution name the base does not hold is left out by name and the rest is written, and the
   registration says what was left out. The student is sent the "registration received" email.

Whatever happened, the page shows every student the same thank-you sentence, which the page owns and
an Administrator may edit (default: "Thank you. We have received your registration and will email
you at the address you gave."). The page never says whether an address belongs to a student: anyone
can type anyone's address, and the program's list of students is not public. The email to that
address is where the difference is told.

**The page answers first** (the product owner, 5 November 2026, after the final review). Steps 2 to 4 take a different time for each kind of address (none for an account, one Airtable request for an address the Students table holds, two or three for a new one), so a page that waited for them would let a stranger time which kind an address is, and a slow Airtable would keep a student waiting. So the page sends the thank-you as soon as the guard has taken the registration and it is stored, and steps 2 to 4 run straight after, once the answer has gone out, in the same order. Where the server cannot finish the answer early, they are left to the hourly job.

**A second daily limit** (the product owner, 5 November 2026, after the final review). Past the site-wide ceiling (40 a day) a registration waits for the hourly job, which writes at most 40 a run. Past 200 registrations in a day, a registration is still stored and the student sees the same thank-you, but it waits for an Administrator: the hourly job does not write it, and the Registrations tab shows it as held, with **Write it now**. A flood that slips past the guard then stays out of the Students table until a person looks.

When Airtable cannot be reached at step 3 or 4, the registration is kept on the site as waiting and
the page shows the same thank-you, so a student is never asked to send the form twice. A new scheduled
job (the plugin has no unattended retry to copy: the mentor activation's is a button) tries each
waiting registration again every hour, under a lock, with steps 2 and 3 checked again before it
writes, since time has passed. After 72 hours without success it stops and the registration shows as
**Could not be written**, with **Write it now** for an Administrator (section 6). No email goes to the
student while a registration waits: each email of section 5 is sent once its outcome is known, at
once in the usual case, or when the retry settles it.

### 4.1 What Airtable does next (unchanged)

The Students automations fire on what a row holds, not on the form being sent, so a row the site
writes sets off what a row from the Airtable form does today, as read on 5 November 2026: the tutor
email filled with the institution's when it is empty; the program manager's "New student registered"
notice where one is set up; the first-contact emails some institutions have. The program manager then
assigns the mentor and the track, and the welcome email, the site's account and the invitation
follow as they do today.

## 5. The emails

Three new emails in the Mentor applications' fashion, listed in Tools > Emails, editable there, sent
from the site's address with the program's Reply-To. Their default words:

**Registration received** (to the address given, after step 4):

> Subject: Registration received - WordPress Credits Program
>
> Hi {student_name},
>
> Thank you for registering for the WordPress Credits Program. We have received your registration
> for {institution_name}.
>
> A program manager will be in touch by email with the next steps, including your mentor and your
> course.
>
> If you have any questions in the meantime, reply to this email.

**Already has an account** (step 2):

> Subject: You already have an account - WordPress Credits Program
>
> Hi,
>
> Someone, probably you, just registered for the WordPress Credits Program with this address. You
> already have an account on {site_name}, so there is nothing more to fill in: sign in here.
>
> {login_link}
>
> If you do not remember your password, set a new one here: {lost_password_link}
>
> If you would like to join another course, reply to this email and a program manager will help.
>
> If this was not you, you can ignore this email.

**Already registered** (step 3):

> Subject: You are already registered - WordPress Credits Program
>
> Hi,
>
> Someone, probably you, just registered for the WordPress Credits Program with this address. We
> already have your registration, so there is nothing more to fill in. A program manager will be in
> touch by email with the next steps.
>
> If you have any questions, reply to this email. If this was not you, you can ignore this email.

These emails count against the guard's daily ceiling on acknowledgements, as the other forms' do.

## 6. Where Administrators see registrations

A **Registrations** tab on the Students screen in wp-admin, listing the registrations newest first
with the date, the institution and what happened to each: **Written to Airtable** (with a link to the
row), **Already has an account** (with a link to the account), **Already registered** (with a link to
the existing row, or to the earlier registration), **Waiting to be written** (Airtable could not be
reached; tried again every hour), **Could not be written** (with **Write it now**), or **Spam**. The site
holds at most one registration per address that is waiting or written: a second submission from the
same address is answered as already registered and kept as a short entry under the first. Open one to read the answers and what was agreed to. A spam row has
**Put back** (it is then handled from step 2, as if just sent) and **Delete for good**.

Each registration is one private post (a post type of the plugin's own) holding the answers, the
consent evidence and what happened. It is kept while it is useful and then deleted by the same
retention settings the institution and sponsor applications use (spam after 30 days, the rest after a
year, by default). The Airtable row, once written, is Airtable's and is never changed or deleted by
the site.

No notice is mailed to Administrators: the program manager hears of a new student from Airtable, as
today.

## 7. Settings and switching it on

On Settings > Students and mentors, a section **Student registrations**:

- **Registrations from students** - **Take student registrations through the form on this site**. Off
  by default. While it is off, the page says registrations are closed, and an Administrator who opens
  it is told why.
- **Institutions offered** - the `Current Stage` values whose institutions the form lists, `Confirmed`
  by default.
- The page's address under the switch.

The launch is a person's: switch it on, try it once with the owner's own test address, then share the
link `/student-registration/` with the institutions and coordinators in place of the Airtable form's,
and replace it on any handbook page that links the Airtable form (none did on 5 November 2026; the
handbook links the Students Interest Form, which stays). The Airtable form is closed by a person,
later.

## 8. Goals, non-goals and anti-goals

**Goals:** students register on the site with the same questions; the program manager's work after a
registration is unchanged; the link can replace the Airtable form's everywhere.

**Non-goals:** a track question; creating accounts or sending invitations (the students sync does that
once a track is assigned, as today); assigning mentors; replacing the Students Interest Form; moving
students off Airtable; an institution importing its students (the Institution Dashboard does that
already).

**Anti-goals (a failure even if it works):**

- a second Students row for an address that has one, or for an address with an account on the site;
- a page that tells a stranger whether an address belongs to a student;
- a spam submission that reaches Airtable or sends an email;
- a student told to send the form again because Airtable was slow;
- the welcome email or an invitation sent by the registration itself.

## 9. Built on what the plugin has

- The mentor application form (`WPCPM_Mentor_Application_Form`): the page, the block and shortcode,
  `fields()`, the cleaning and requiredness, the redraw with answers kept, the thank-you sentence.
- `WPCPM_Form_Guard`: every guard check and the consent evidence.
- The institution import (`WPCPM_Institution_Import`): `clean_row()`'s cleaning of a student's
  answers, the field-of-study choices, and the duplicate checks against the base.
- The mentor activation's Airtable row (`WPCPM_Mentor_Airtable_Row`): shaping cells by column type,
  linked names, leaving out what the base does not hold, the retry.
- The Emails tool's registry for the three emails.

## 10. Tests

Each behavior of sections 3 to 7 has a check in a new `bin/test-student-registration.php`, in the
style of `bin/test-mentor-application-form.php`, Airtable faked: the fourteen questions with their
labels, help, order and requiredness; each refusal; the three paths of section 4 (account, existing
row, new row) with the email each sends and that nothing else is written; the cells of a written row,
column by column, including `Tutor ` with its space and an empty `Status`; the same page sentence on
every path; spam reaching neither Airtable nor the mail; Airtable unreachable leading to a waiting
registration and a retry; the switch. A trial on the Docker copy with Airtable faked comes before the
release, and the first real write on live is a test registration with the owner's test address, on the
owner's yes.

## 11. Left to the builder

The post type's name and keys, the tab's columns and views, the cached languages' lifetime, the page block's attributes, the CSS (the mentor form's look), and the wording of
refusals not quoted here, in the site's voice.

## 12. Open questions

None. The one left after the builder check (which institution stages) is decision 4.
